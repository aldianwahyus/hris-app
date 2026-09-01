<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Overtime\Application\ProcessOvertimePaymentBatch;
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
 * Pembayaran Lembur MASSAL — tahap SETELAH 2 tahap persetujuan
 * (ApprovalQueueController). Pembagi wewenang PERSIS instruksi bisnis
 * (bukan pola OFFICE/OFFICE_TREE/BANK_WIDE generik AccessPolicy —
 * kasusnya spesifik berdasar TIPE kantor pemohon):
 *  - Pemohon di kantor head_office → HANYA Admin HC (hr_approver),
 *    dipilih PER DIVISI (?division=...).
 *  - Pemohon di kantor branch/sub_branch → HANYA Admin Cabang
 *    (hr_admin) PEMILIK kantor itu, langsung semua pegawai (tidak ada
 *    tahap divisi — satu kantor cabang sudah cukup sempit).
 * Satu batch = satu SPKL baru + satu pejabat pengusul + dua akun
 * jurnal, mencakup banyak pengajuan sekaligus (ProcessOvertimePaymentBatch).
 *
 * @phpstan-type PaymentBatchRow object{
 *   id: string, spkl_number: string, payer_scope: string, office_id: ?string,
 *   division: ?string, signatory_employee_id: string, tax_rate_percent: string,
 *   total_gross_cents: int, total_tax_cents: int, total_net_cents: int,
 *   signatory_name: string, signatory_position_id: ?string,
 *   expense_account_name: string, expense_account_code: string,
 *   tax_account_name: string, tax_account_code: string, office_name: ?string,
 *   office_address: ?string,
 * }
 */
final class OvertimeDisbursementController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ProcessOvertimePaymentBatch $processBatch,
    ) {}

    /** hr_approver — kantor pusat, dipilih per divisi. */
    public function indexForHc(Request $request): View
    {
        $division = $request->query('division');

        if ($division === null) {
            // reorder(): baseQuery() sudah pasang orderBy('r.decided_at') untuk
            // tampilan per-baris — tidak valid di query GROUP BY ini (Postgres
            // menolak ORDER BY kolom yang bukan bagian GROUP BY/agregat).
            $divisions = $this->baseQuery()
                ->where('o.office_type', 'head_office')
                ->select('e.division', DB::raw('count(*) as jumlah'), DB::raw('sum(r.amount_cents) as total_cents'))
                ->groupBy('e.division')
                ->reorder('e.division')
                ->get();

            return view('admin.overtime-payment-divisions', ['divisions' => $divisions]);
        }

        $rows = $this->baseQuery()
            ->where('o.office_type', 'head_office')
            ->where('e.division', $division)
            ->get();

        $signatories = DB::table('emp_employees')->where('division', $division)->orderBy('full_name')->get(['id', 'full_name', 'nrp']);
        $accounts = $this->activeJournalAccounts();

        return view('admin.overtime-payment-queue', [
            'rows' => $rows, 'lingkup' => "Kantor Pusat — {$division}", 'payerScope' => 'hc',
            'division' => $division, 'officeId' => null, 'signatories' => $signatories, 'accounts' => $accounts,
        ]);
    }

    /** Untuk badge notifikasi sidebar — HC bank-wide (kantor pusat). */
    public function pendingCountHc(): int
    {
        return $this->baseQuery()->where('o.office_type', 'head_office')->count();
    }

    /** Untuk badge notifikasi sidebar — Admin Cabang, kantornya sendiri. */
    public function pendingCountBranch(): int
    {
        $officeId = $this->actor->officeId();

        if ($officeId === null) {
            return 0;
        }

        return $this->baseQuery()
            ->where('e.office_id', $officeId)
            ->whereIn('o.office_type', ['branch', 'sub_branch'])
            ->count();
    }

    public function processBatchForHc(Request $request): RedirectResponse
    {
        $validated = $this->validateBatchRequest($request);
        $division = $request->string('division')->toString();

        try {
            $batchId = $this->processBatch->handle(
                requestIds: $validated['request_ids'],
                signatoryEmployeeId: $validated['signatory_employee_id'],
                expenseAccountId: $validated['journal_expense_account_id'],
                taxAccountId: $validated['journal_tax_account_id'],
                payerScope: 'hc',
                officeId: null,
                division: $division,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException $e) {
            return redirect()->route('admin.overtime-disbursement-queue', ['division' => $division])->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.overtime-payment-batch.show', $batchId)->with('sukses', 'Pembayaran lembur massal tercatat.');
    }

    /** hr_admin — hanya lembur cabang/KCP miliknya sendiri, langsung semua pegawai. */
    public function indexForBranch(): View
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $rows = $this->baseQuery()
            ->where('e.office_id', $officeId)
            ->whereIn('o.office_type', ['branch', 'sub_branch'])
            ->get();

        $signatories = DB::table('emp_employees')->where('office_id', $officeId)->orderBy('full_name')->get(['id', 'full_name', 'nrp']);
        $accounts = $this->activeJournalAccounts();

        return view('admin.overtime-payment-queue', [
            'rows' => $rows, 'lingkup' => 'Kantor Anda', 'payerScope' => 'branch',
            'division' => null, 'officeId' => $officeId, 'signatories' => $signatories, 'accounts' => $accounts,
        ]);
    }

    public function processBatchForBranch(Request $request): RedirectResponse
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $validated = $this->validateBatchRequest($request);

        // Pagar lingkup: seluruh request_ids yang dikirim harus benar
        // milik kantor sendiri — cegah admin cabang membayar lembur
        // kantor lain lewat request yang dimanipulasi di form.
        $foreignCount = DB::table('ovt_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->whereIn('r.id', $validated['request_ids'])
            ->where('e.office_id', '!=', $officeId)
            ->count();
        abort_if($foreignCount > 0, 404);

        try {
            $batchId = $this->processBatch->handle(
                requestIds: $validated['request_ids'],
                signatoryEmployeeId: $validated['signatory_employee_id'],
                expenseAccountId: $validated['journal_expense_account_id'],
                taxAccountId: $validated['journal_tax_account_id'],
                payerScope: 'branch',
                officeId: $officeId,
                division: null,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException $e) {
            return redirect()->route('hr.overtime-disbursement.index')->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.overtime-payment-batch.show', $batchId)->with('sukses', 'Pembayaran lembur massal tercatat.');
    }

    public function showBatch(string $id): View
    {
        /** @var PaymentBatchRow|null $batch */
        $batch = $this->batchQuery()->where('b.id', $id)->first();
        abort_if($batch === null, 404);
        $this->guardBatchAccess($batch);

        $items = DB::table('ovt_payment_batch_items as i')
            ->join('emp_employees as e', 'e.id', '=', 'i.employee_id')
            ->where('i.batch_id', $id)
            ->select('i.*', 'e.full_name', 'e.nrp')
            ->orderBy('e.full_name')
            ->get();

        return view('admin.overtime-payment-batch-show', compact('batch', 'items'));
    }

    public function printMemo(string $id): Response
    {
        [$batch, $items, $spklSlug, $officeAddress] = $this->batchWithItemsForPrint($id);

        // Rujukan SK Direksi tarif lembur — diambil DINAMIS dari
        // cfg_parameter_values (bukan diketik ulang di templat) supaya
        // selalu mencerminkan SK yang benar-benar berlaku, sama seperti
        // dipakai KonfigurasiParameter untuk tarif itu sendiri.
        $skDireksi = DB::table('cfg_parameters as p')
            ->join('cfg_parameter_values as v', 'v.parameter_id', '=', 'p.id')
            ->where('p.code', 'OVT_RATE_MGR_SPV_OFC')
            ->whereNull('v.deleted_at')
            ->orderByDesc('v.effective_from')
            ->value('v.source_document');

        // stream(), bukan download() — tampil langsung di tab browser
        // (pratinjau PDF bawaan browser, lengkap dengan tombol cetak
        // bawaannya) alih-alih memaksa unduh berkas.
        return Pdf::loadView('admin.overtime-payment-memo', compact('batch', 'items', 'officeAddress', 'skDireksi'))
            ->stream("Memo-Internal-{$spklSlug}.pdf");
    }

    public function printNotaDebet(string $id): Response
    {
        [$batch, $items, $spklSlug, $officeAddress] = $this->batchWithItemsForPrint($id);

        return Pdf::loadView('admin.overtime-payment-nota-debet', compact('batch', 'items', 'officeAddress'))
            ->stream("Nota-Debet-{$spklSlug}.pdf");
    }

    public function printJurnalSlip(string $id): Response
    {
        [$batch, $items, $spklSlug, $officeAddress] = $this->batchWithItemsForPrint($id);

        return Pdf::loadView('admin.overtime-payment-jurnal-slip', compact('batch', 'items', 'officeAddress'))
            ->stream("Jurnal-Slip-{$spklSlug}.pdf");
    }

    /**
     * Rincian rekening penerima — dokumen TERSENDIRI (bukan lagi tabel
     * di dalam Nota Debet, lihat overtime-payment-nota-debet.blade.php)
     * supaya persis format resmi Bank NTB Syariah ("LAMPIRAN PENERIMA
     * UANG LEMBUR" adalah cetakan sendiri).
     */
    public function printLampiranPenerima(string $id): Response
    {
        [$batch, $items, $spklSlug, $officeAddress] = $this->batchWithItemsForPrint($id);

        return Pdf::loadView('admin.overtime-payment-lampiran-penerima', compact('batch', 'items', 'officeAddress'))
            ->stream("Lampiran-Penerima-{$spklSlug}.pdf");
    }

    /** @return array{0: PaymentBatchRow, 1: Collection<int, \stdClass>, 2: string, 3: ?string} */
    private function batchWithItemsForPrint(string $id): array
    {
        /** @var PaymentBatchRow|null $batch */
        $batch = $this->batchQuery()->where('b.id', $id)->first();
        abort_if($batch === null, 404);
        $this->guardBatchAccess($batch);

        // planned_hours/work_date/spkl_number BUKAN kolom ovt_payment_batch_items
        // (cuma gross/tax/net cents) — join TAMBAHAN ke ovt_requests lewat
        // ovt_request_id (FK yang sudah ada) untuk Memo Internal ("Lama
        // Hari/Jam", "Uang Lembur" per jam, rujukan nomor SPKL pengajuan).
        $items = DB::table('ovt_payment_batch_items as i')
            ->join('emp_employees as e', 'e.id', '=', 'i.employee_id')
            ->join('ovt_requests as r', 'r.id', '=', 'i.ovt_request_id')
            ->where('i.batch_id', $id)
            ->select('i.*', 'e.full_name', 'e.nrp', 'r.spkl_number', 'r.work_date', 'r.planned_hours')
            ->orderBy('e.full_name')
            ->get();

        return [$batch, $items, str_replace('/', '-', $batch->spkl_number), $this->resolveOfficeAddress($batch)];
    }

    /**
     * Alamat kantor untuk header dokumen cetak — kantor pusat (untuk
     * batch payer_scope='hc', office_id NULL) diambil terpisah karena
     * leftJoin di batchQuery() tidak menghasilkan apa pun untuk baris
     * itu; batch cabang pakai alamat kantornya sendiri dari join yang
     * sudah ada.
     *
     * @param  PaymentBatchRow  $batch
     */
    private function resolveOfficeAddress(object $batch): ?string
    {
        if ($batch->payer_scope === 'hc') {
            return DB::table('md_offices')->where('office_type', 'head_office')->value('address');
        }

        return $batch->office_address;
    }

    /** @param PaymentBatchRow $batch */
    private function guardBatchAccess(object $batch): void
    {
        // Batch HC (kantor pusat) hanya boleh dilihat hr_approver —
        // Admin Cabang tidak boleh melihat/mencetak dokumen batch HC
        // walau tahu ID-nya. Batch cabang boleh dilihat pemilik
        // kantornya sendiri ATAU hr_approver (pengawasan bank-wide).
        if ($batch->payer_scope === 'hc') {
            abort_unless(in_array('hr_approver', $this->actor->roles(), true), 404);

            return;
        }

        abort_unless($batch->office_id === $this->actor->officeId() || in_array('hr_approver', $this->actor->roles(), true), 404);
    }

    private function batchQuery(): Builder
    {
        return DB::table('ovt_payment_batches as b')
            ->join('emp_employees as s', 's.id', '=', 'b.signatory_employee_id')
            ->join('fin_journal_accounts as fe', 'fe.id', '=', 'b.journal_expense_account_id')
            ->join('fin_journal_accounts as ft', 'ft.id', '=', 'b.journal_tax_account_id')
            ->leftJoin('md_offices as o', 'o.id', '=', 'b.office_id')
            ->select(
                'b.*', 's.full_name as signatory_name', 's.position_id as signatory_position_id',
                'fe.name as expense_account_name', 'fe.code as expense_account_code',
                'ft.name as tax_account_name', 'ft.code as tax_account_code',
                'o.name as office_name', 'o.address as office_address',
            );
    }

    /** @return array{request_ids: array<int, string>, signatory_employee_id: string, journal_expense_account_id: string, journal_tax_account_id: string} */
    private function validateBatchRequest(Request $request): array
    {
        return $request->validate([
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['uuid', 'exists:ovt_requests,id'],
            'signatory_employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'journal_expense_account_id' => ['required', 'uuid', 'exists:fin_journal_accounts,id'],
            'journal_tax_account_id' => ['required', 'uuid', 'exists:fin_journal_accounts,id'],
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
        return DB::table('ovt_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->where('r.status', 'approved')
            ->select(
                'r.id', 'r.spkl_number', 'r.overtime_type', 'r.work_date',
                'r.payable_hours', 'r.amount_cents', 'r.decided_at',
                'e.full_name', 'e.nrp', 'p.name as position_name', 'o.name as office_name',
            )
            ->orderBy('r.decided_at');
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
