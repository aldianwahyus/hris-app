<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Payroll\Application\ProcessBekalCutiPaymentBatch;
use App\Shared\Audit\Domain\AuditActor;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pembayaran Bekal Cuti MASSAL — pola PERSIS
 * OvertimeDisbursementController (checklist → pejabat pengusul → akun
 * jurnal → proses batch → cetak), pembagi wewenang identik (HC =
 * kantor pusat dipilih per divisi, Admin Cabang = kantornya sendiri
 * langsung semua pegawai). Beda: (1) pajak PPh 21 TER, bukan flat %
 * (lihat ProcessBekalCutiPaymentBatch), (2) jumlah BRUTO bekal cuti
 * OTOMATIS = 1× gaji terakhir (SK Direksi BPP/1087/03/64/2026 — TIDAK
 * diisi manual admin; queue hanya menampilkan pratinjau angkanya lewat
 * ProcessBekalCutiPaymentBatch::previewGajiKotorCents()), (3) HANYA 2
 * dokumen cetak (Memo Internal + SATU Nota Debet gabungan 2 baris
 * debet/kredit) — tidak ada Jurnal Slip terpisah seperti lembur.
 *
 * @phpstan-type BekalCutiBatchRow object{
 *   id: string, reference_number: string, payer_scope: string, office_id: ?string,
 *   division: ?string, signatory_employee_id: string,
 *   total_gross_cents: int, total_tax_cents: int, total_net_cents: int,
 *   signatory_name: string, signatory_position_id: ?string,
 *   leave_expense_account_name: string, leave_expense_account_code: string,
 *   tax_expense_account_name: string, tax_expense_account_code: string,
 *   tax_holding_account_name: string, tax_holding_account_code: string,
 *   office_name: ?string, office_address: ?string,
 * }
 */
final class BekalCutiDisbursementController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ProcessBekalCutiPaymentBatch $processBatch,
    ) {}

    /** hr_approver — kantor pusat, dipilih per divisi. */
    public function indexForHc(Request $request): View
    {
        $division = $request->query('division');

        if ($division === null) {
            $divisions = $this->baseQuery()
                ->where('o.office_type', 'head_office')
                ->select('e.division', DB::raw('count(*) as jumlah'))
                ->groupBy('e.division')
                ->reorder('e.division')
                ->get();

            return view('admin.bekal-cuti-payment-divisions', ['divisions' => $divisions]);
        }

        $rows = $this->withGajiKotorPreview($this->baseQuery()
            ->where('o.office_type', 'head_office')
            ->where('e.division', $division)
            ->get());

        $signatories = DB::table('emp_employees')->where('division', $division)->orderBy('full_name')->get(['id', 'full_name', 'nrp']);
        $accounts = $this->activeJournalAccounts();

        return view('admin.bekal-cuti-payment-queue', [
            'rows' => $rows, 'lingkup' => "Kantor Pusat — {$division}", 'payerScope' => 'hc',
            'division' => $division, 'officeId' => null, 'signatories' => $signatories, 'accounts' => $accounts,
        ]);
    }

    /** Untuk badge notifikasi sidebar — HC bank-wide. */
    public function pendingCountHc(): int
    {
        return $this->baseQuery()->count();
    }

    /** Untuk badge notifikasi sidebar — Admin Cabang, kantornya sendiri. */
    public function pendingCountBranch(): int
    {
        $officeId = $this->actor->officeId();

        return $officeId === null ? 0 : $this->baseQuery()->where('e.office_id', $officeId)->count();
    }

    public function processBatchForHc(Request $request): RedirectResponse
    {
        $division = $request->string('division')->toString();

        try {
            $validated = $this->validateBatchRequest($request);
            $batchId = $this->processBatch->handle(
                disbursementIds: $validated['disbursement_ids'],
                signatoryEmployeeId: $validated['signatory_employee_id'],
                leaveExpenseAccountId: $validated['journal_leave_expense_account_id'],
                taxExpenseAccountId: $validated['journal_tax_expense_account_id'],
                taxHoldingAccountId: $validated['journal_tax_holding_account_id'],
                payerScope: 'hc',
                officeId: null,
                division: $division,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException $e) {
            return redirect()->route('admin.bekal-cuti-queue', ['division' => $division])->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.bekal-cuti-payment-batch.show', $batchId)->with('sukses', 'Pembayaran bekal cuti massal tercatat.');
    }

    /** hr_admin — hanya bekal cuti kantornya sendiri, langsung semua pegawai. */
    public function indexForBranch(): View
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $rows = $this->withGajiKotorPreview($this->baseQuery()->where('e.office_id', $officeId)->get());
        $signatories = DB::table('emp_employees')->where('office_id', $officeId)->orderBy('full_name')->get(['id', 'full_name', 'nrp']);
        $accounts = $this->activeJournalAccounts();

        return view('admin.bekal-cuti-payment-queue', [
            'rows' => $rows, 'lingkup' => 'Kantor Anda', 'payerScope' => 'branch',
            'division' => null, 'officeId' => $officeId, 'signatories' => $signatories, 'accounts' => $accounts,
        ]);
    }

    public function processBatchForBranch(Request $request): RedirectResponse
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        try {
            $validated = $this->validateBatchRequest($request);

            // Pagar lingkup: seluruh baris yang dikirim harus benar milik
            // kantor sendiri — cegah admin cabang membayar bekal cuti
            // kantor lain lewat form yang dimanipulasi.
            $foreignCount = DB::table('pay_bekal_cuti_disbursements as b')
                ->join('emp_employees as e', 'e.id', '=', 'b.employee_id')
                ->whereIn('b.id', $validated['disbursement_ids'])
                ->where('e.office_id', '!=', $officeId)
                ->count();
            abort_if($foreignCount > 0, 404);

            $batchId = $this->processBatch->handle(
                disbursementIds: $validated['disbursement_ids'],
                signatoryEmployeeId: $validated['signatory_employee_id'],
                leaveExpenseAccountId: $validated['journal_leave_expense_account_id'],
                taxExpenseAccountId: $validated['journal_tax_expense_account_id'],
                taxHoldingAccountId: $validated['journal_tax_holding_account_id'],
                payerScope: 'branch',
                officeId: $officeId,
                division: null,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException $e) {
            return redirect()->route('hr.bekal-cuti.index')->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.bekal-cuti-payment-batch.show', $batchId)->with('sukses', 'Pembayaran bekal cuti massal tercatat.');
    }

    public function showBatch(string $id): View
    {
        /** @var BekalCutiBatchRow|null $batch */
        $batch = $this->batchQuery()->where('b.id', $id)->first();
        abort_if($batch === null, 404);
        $this->guardBatchAccess($batch);

        $items = $this->batchItems($id);

        return view('admin.bekal-cuti-payment-batch-show', compact('batch', 'items'));
    }

    public function printMemo(string $id): Response
    {
        [$batch, $items, $slug, $officeAddress] = $this->batchWithItemsForPrint($id);

        return Pdf::loadView('admin.bekal-cuti-payment-memo', compact('batch', 'items', 'officeAddress'))
            ->stream("Memo-Internal-Bekal-Cuti-{$slug}.pdf");
    }

    public function printNotaDebet(string $id): Response
    {
        [$batch, $items, $slug, $officeAddress] = $this->batchWithItemsForPrint($id);

        return Pdf::loadView('admin.bekal-cuti-payment-nota-debet', compact('batch', 'items', 'officeAddress'))
            ->stream("Nota-Debet-Bekal-Cuti-{$slug}.pdf");
    }

    public function printLampiranPenerima(string $id): Response
    {
        [$batch, $items, $slug, $officeAddress] = $this->batchWithItemsForPrint($id);

        // Landscape: 11 kolom (NO s.d. No. Simpeda) tidak muat di potret A4.
        return Pdf::loadView('admin.bekal-cuti-payment-lampiran-penerima', compact('batch', 'items', 'officeAddress'))
            ->setPaper('a4', 'landscape')
            ->stream("Lampiran-Penerima-Bekal-Cuti-{$slug}.pdf");
    }

    /** @return array{0: BekalCutiBatchRow, 1: Collection<int, \stdClass>, 2: string, 3: ?string} */
    private function batchWithItemsForPrint(string $id): array
    {
        /** @var BekalCutiBatchRow|null $batch */
        $batch = $this->batchQuery()->where('b.id', $id)->first();
        abort_if($batch === null, 404);
        $this->guardBatchAccess($batch);

        return [$batch, $this->batchItems($id), str_replace('/', '-', $batch->reference_number), $this->resolveOfficeAddress($batch)];
    }

    /**
     * Alamat kantor untuk header dokumen cetak — kantor pusat (untuk
     * batch payer_scope='hc', office_id NULL) diambil terpisah, sama
     * seperti OvertimeDisbursementController::resolveOfficeAddress().
     *
     * @param  BekalCutiBatchRow  $batch
     */
    private function resolveOfficeAddress(object $batch): ?string
    {
        if ($batch->payer_scope === 'hc') {
            return DB::table('md_offices')->where('office_type', 'head_office')->value('address');
        }

        return $batch->office_address;
    }

    /** @return Collection<int, \stdClass> */
    private function batchItems(string $batchId): Collection
    {
        return DB::table('bkl_payment_batch_items as i')
            ->join('emp_employees as e', 'e.id', '=', 'i.employee_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->join('pay_bekal_cuti_disbursements as d', 'd.id', '=', 'i.bekal_cuti_disbursement_id')
            ->where('i.batch_id', $batchId)
            ->select('i.*', 'e.full_name', 'e.nrp', 'e.division', 'p.name as position_name', 'd.year')
            ->orderBy('e.full_name')
            ->get();
    }

    /** @param BekalCutiBatchRow $batch */
    private function guardBatchAccess(object $batch): void
    {
        if ($batch->payer_scope === 'hc') {
            abort_unless(in_array('hr_approver', $this->actor->roles(), true), 404);

            return;
        }

        abort_unless($batch->office_id === $this->actor->officeId() || in_array('hr_approver', $this->actor->roles(), true), 404);
    }

    private function batchQuery(): Builder
    {
        return DB::table('bkl_payment_batches as b')
            ->join('emp_employees as s', 's.id', '=', 'b.signatory_employee_id')
            ->join('fin_journal_accounts as fle', 'fle.id', '=', 'b.journal_leave_expense_account_id')
            ->join('fin_journal_accounts as fte', 'fte.id', '=', 'b.journal_tax_expense_account_id')
            ->join('fin_journal_accounts as fth', 'fth.id', '=', 'b.journal_tax_holding_account_id')
            ->leftJoin('md_offices as o', 'o.id', '=', 'b.office_id')
            ->select(
                'b.*', 's.full_name as signatory_name', 's.position_id as signatory_position_id',
                'fle.name as leave_expense_account_name', 'fle.code as leave_expense_account_code',
                'fte.name as tax_expense_account_name', 'fte.code as tax_expense_account_code',
                'fth.name as tax_holding_account_name', 'fth.code as tax_holding_account_code',
                'o.name as office_name', 'o.address as office_address',
            );
    }

    /** @return array{disbursement_ids: array<int, string>, signatory_employee_id: string, journal_leave_expense_account_id: string, journal_tax_expense_account_id: string, journal_tax_holding_account_id: string} */
    private function validateBatchRequest(Request $request): array
    {
        return $request->validate([
            'disbursement_ids' => ['required', 'array', 'min:1'],
            'disbursement_ids.*' => ['uuid', 'exists:pay_bekal_cuti_disbursements,id'],
            'signatory_employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'journal_leave_expense_account_id' => ['required', 'uuid', 'exists:fin_journal_accounts,id'],
            'journal_tax_expense_account_id' => ['required', 'uuid', 'exists:fin_journal_accounts,id'],
            'journal_tax_holding_account_id' => ['required', 'uuid', 'exists:fin_journal_accounts,id'],
        ]);
    }

    /** @return array<string, Collection<int, \stdClass>> */
    private function activeJournalAccounts(): array
    {
        return [
            'beban' => DB::table('fin_journal_accounts')->where('category', 'beban')->where('is_active', true)->orderBy('name')->get(),
            'penampungan_pajak' => DB::table('fin_journal_accounts')->where('category', 'penampungan_pajak')->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function baseQuery(): Builder
    {
        return DB::table('pay_bekal_cuti_disbursements as b')
            ->join('emp_employees as e', 'e.id', '=', 'b.employee_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('b.status', 'pending')
            ->select(
                'b.id', 'b.year', 'e.full_name', 'e.nrp', 'e.division', 'o.name as office_name',
                'e.person_grade', 'e.salary_step', 'e.tunjangan_jabatan_cents', 'e.tunjangan_penyesuaian_cents',
            )
            ->orderBy('b.created_at');
    }

    /**
     * Menempelkan pratinjau jumlah bekal cuti (1x gaji terakhir) ke
     * tiap baris untuk ditampilkan di antrean — HANYA pratinjau,
     * angka final tetap dihitung ulang oleh ProcessBekalCutiPaymentBatch
     * saat batch benar-benar diproses (AsOfDate saat proses, bukan
     * saat halaman ini dibuka, yang berlaku).
     *
     * @param  Collection<int, \stdClass>  $rows
     * @return Collection<int, \stdClass>
     */
    private function withGajiKotorPreview(Collection $rows): Collection
    {
        return $rows->map(function ($r) {
            $r->preview_gross_cents = $this->processBatch->previewGajiKotorCents(
                $r->person_grade !== null ? (int) $r->person_grade : null,
                $r->salary_step !== null ? (int) $r->salary_step : null,
                (int) $r->tunjangan_jabatan_cents,
                (int) $r->tunjangan_penyesuaian_cents,
            );

            return $r;
        });
    }

    private function currentAuditActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
