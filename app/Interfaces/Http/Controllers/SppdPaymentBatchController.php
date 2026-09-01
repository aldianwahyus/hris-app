<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Payroll\Application\ProcessSppdPaymentBatch;
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
 * Pembayaran SPPD Massal — SETELAH berkas Surat Jalan/Rincian Lumpsum
 * (SppdMemoController) kembali ke Admin HC/Admin Cabang. Batch di-scope
 * PER GRUP MEMO (spd_memo_groups), bukan per divisi seperti Lembur —
 * lihat ProcessSppdPaymentBatch untuk alasan lengkap. Struktur controller
 * MENIRU OvertimeDisbursementController (indexForHc/indexForBranch,
 * showMemoQueue, processBatchForHc/Branch, showBatch, print*,
 * guardBatchAccess, batchQuery) — TIGA akun jurnal (beban lumpsum +
 * beban PPh21 + penampungan pajak, pola sama bekal-cuti-payment-queue),
 * bukan satu seperti sebelumnya (SPPD DIKENAKAN PPh21 TER, lihat
 * ProcessSppdPaymentBatch).
 */
final class SppdPaymentBatchController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ProcessSppdPaymentBatch $processBatch,
    ) {}

    /** hr_approver — daftar grup memo bank-wide yang masih ada traveler approved. */
    public function indexForHc(): View
    {
        $groups = $this->unpaidGroupsQuery()->where('g.payer_scope', 'hc')->get();

        return view('admin.sppd-payment-groups', ['groups' => $groups, 'lingkup' => 'Seluruh Bank']);
    }

    /** hr_admin — daftar grup memo kantornya sendiri yang masih ada traveler approved. */
    public function indexForBranch(): View
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $groups = $this->unpaidGroupsQuery()->where('g.payer_scope', 'branch')->where('g.office_id', $officeId)->get();

        return view('admin.sppd-payment-groups', ['groups' => $groups, 'lingkup' => 'Kantor Anda']);
    }

    public function showMemoQueue(string $memoGroupId): View
    {
        /** @var object{payer_scope: string, office_id: ?string}|null $group */
        $group = DB::table('spd_memo_groups')->where('id', $memoGroupId)->first();
        abort_if($group === null, 404);
        $this->guardGroupAccess($group);

        $travelers = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->where('r.memo_group_id', $memoGroupId)
            ->where('r.status', 'approved')
            ->select(
                'r.id', 'r.uang_makan_cents', 'r.uang_saku_cents', 'r.estimasi_hotel_cents', 'r.hotel_kompensasi_cents',
                'r.estimasi_angkutan_setempat_cents', 'r.estimasi_transportasi_tujuan_cents',
                'r.uang_makan_h1_cents', 'r.uang_saku_h1_cents', 'r.uang_makan_konsumsi_cents',
                'e.full_name', 'e.nrp',
            )
            ->orderBy('e.full_name')
            ->get();

        $signatories = $group->payer_scope === 'hc'
            ? DB::table('emp_employees')->orderBy('full_name')->get(['id', 'full_name', 'nrp'])
            : DB::table('emp_employees')->where('office_id', $group->office_id)->orderBy('full_name')->get(['id', 'full_name', 'nrp']);

        $accounts = $this->activeJournalAccounts();

        return view('admin.sppd-payment-queue', compact('group', 'travelers', 'signatories', 'accounts'));
    }

    public function processBatchForHc(Request $request): RedirectResponse
    {
        $memoGroupId = $request->string('memo_group_id')->toString();
        /** @var object{payer_scope: string, office_id: ?string}|null $group */
        $group = DB::table('spd_memo_groups')->where('id', $memoGroupId)->first();
        abort_if($group === null || $group->payer_scope !== 'hc', 404);

        $validated = $this->validateBatchRequest($request);

        try {
            $batchId = $this->processBatch->handle(
                requestIds: $validated['request_ids'],
                memoGroupId: $memoGroupId,
                signatoryEmployeeId: $validated['signatory_employee_id'],
                expenseAccountId: $validated['journal_expense_account_id'],
                taxExpenseAccountId: $validated['journal_tax_expense_account_id'],
                taxAccountId: $validated['journal_tax_account_id'],
                payerScope: 'hc',
                officeId: null,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException $e) {
            return redirect()->route('admin.sppd-payment.queue', $memoGroupId)->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.sppd-payment-batch.show', $batchId)->with('sukses', 'Pembayaran SPPD massal tercatat.');
    }

    public function processBatchForBranch(Request $request): RedirectResponse
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $memoGroupId = $request->string('memo_group_id')->toString();
        /** @var object{payer_scope: string, office_id: ?string}|null $group */
        $group = DB::table('spd_memo_groups')->where('id', $memoGroupId)->first();
        abort_if($group === null || $group->payer_scope !== 'branch' || $group->office_id !== $officeId, 404);

        $validated = $this->validateBatchRequest($request);

        // Pagar lingkup: seluruh request_ids yang dikirim harus benar milik
        // kantor sendiri (mirip OvertimeDisbursementController::processBatchForBranch()).
        $foreignCount = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->whereIn('r.id', $validated['request_ids'])
            ->where('e.office_id', '!=', $officeId)
            ->count();
        abort_if($foreignCount > 0, 404);

        try {
            $batchId = $this->processBatch->handle(
                requestIds: $validated['request_ids'],
                memoGroupId: $memoGroupId,
                signatoryEmployeeId: $validated['signatory_employee_id'],
                expenseAccountId: $validated['journal_expense_account_id'],
                taxExpenseAccountId: $validated['journal_tax_expense_account_id'],
                taxAccountId: $validated['journal_tax_account_id'],
                payerScope: 'branch',
                officeId: $officeId,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException $e) {
            return redirect()->route('hr.sppd-payment.queue', $memoGroupId)->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.sppd-payment-batch.show', $batchId)->with('sukses', 'Pembayaran SPPD massal tercatat.');
    }

    public function showBatch(string $id): View
    {
        /** @var object{payer_scope: string, office_id: ?string, office_address: ?string, batch_number: string}|null $batch */
        $batch = $this->batchQuery()->where('b.id', $id)->first();
        abort_if($batch === null, 404);
        $this->guardBatchAccess($batch);

        $items = DB::table('spd_payment_batch_items as i')
            ->join('emp_employees as e', 'e.id', '=', 'i.employee_id')
            ->where('i.batch_id', $id)
            ->select('i.*', 'e.full_name', 'e.nrp')
            ->orderBy('e.full_name')
            ->get();

        return view('admin.sppd-payment-batch-show', compact('batch', 'items'));
    }

    public function printNotaDebet(string $id): Response
    {
        [$batch, $items, $slug, $officeAddress] = $this->batchWithItemsForPrint($id);

        return Pdf::loadView('admin.sppd-payment-nota-debet', compact('batch', 'items', 'officeAddress'))
            ->stream("Nota-Debet-{$slug}.pdf");
    }

    public function printJurnalSlip(string $id): Response
    {
        [$batch, $items, $slug, $officeAddress] = $this->batchWithItemsForPrint($id);

        return Pdf::loadView('admin.sppd-payment-jurnal-slip', compact('batch', 'items', 'officeAddress'))
            ->stream("Jurnal-Slip-{$slug}.pdf");
    }

    /** @return array{0: object, 1: Collection<int, \stdClass>, 2: string, 3: ?string} */
    private function batchWithItemsForPrint(string $id): array
    {
        /** @var object{payer_scope: string, office_id: ?string, office_address: ?string, batch_number: string}|null $batch */
        $batch = $this->batchQuery()->where('b.id', $id)->first();
        abort_if($batch === null, 404);
        $this->guardBatchAccess($batch);

        $items = DB::table('spd_payment_batch_items as i')
            ->join('emp_employees as e', 'e.id', '=', 'i.employee_id')
            ->where('i.batch_id', $id)
            ->select('i.*', 'e.full_name', 'e.nrp')
            ->orderBy('e.full_name')
            ->get();

        $officeAddress = $batch->payer_scope === 'hc'
            ? DB::table('md_offices')->where('office_type', 'head_office')->value('address')
            : $batch->office_address;

        return [$batch, $items, str_replace('/', '-', $batch->batch_number), $officeAddress];
    }

    /** @param object{payer_scope: string, office_id: ?string} $group */
    private function guardGroupAccess(object $group): void
    {
        if ($group->payer_scope === 'hc') {
            abort_unless($this->actor->hasRole('hr_approver'), 404);

            return;
        }

        abort_unless($group->office_id === $this->actor->officeId() || $this->actor->hasRole('hr_approver'), 404);
    }

    /** @param object{payer_scope: string, office_id: ?string, office_address: ?string, batch_number: string} $batch */
    private function guardBatchAccess(object $batch): void
    {
        if ($batch->payer_scope === 'hc') {
            abort_unless($this->actor->hasRole('hr_approver'), 404);

            return;
        }

        abort_unless($batch->office_id === $this->actor->officeId() || $this->actor->hasRole('hr_approver'), 404);
    }

    private function batchQuery(): Builder
    {
        return DB::table('spd_payment_batches as b')
            ->join('spd_memo_groups as g', 'g.id', '=', 'b.memo_group_id')
            ->join('emp_employees as s', 's.id', '=', 'b.signatory_employee_id')
            ->join('fin_journal_accounts as fe', 'fe.id', '=', 'b.journal_expense_account_id')
            ->leftJoin('fin_journal_accounts as fte', 'fte.id', '=', 'b.journal_tax_expense_account_id')
            ->leftJoin('fin_journal_accounts as ft', 'ft.id', '=', 'b.journal_tax_account_id')
            ->leftJoin('md_offices as o', 'o.id', '=', 'b.office_id')
            ->select(
                'b.*', 'g.group_number', 'g.memo_number', 'g.destination', 'g.purpose', 'g.source_division',
                's.full_name as signatory_name', 'fe.name as expense_account_name', 'fe.code as expense_account_code',
                'fte.name as tax_expense_account_name', 'fte.code as tax_expense_account_code',
                'ft.name as tax_account_name', 'ft.code as tax_account_code',
                'o.name as office_name', 'o.address as office_address',
            );
    }

    /**
     * @return array{request_ids: array<int, string>, signatory_employee_id: string,
     *     journal_expense_account_id: string, journal_tax_expense_account_id: string,
     *     journal_tax_account_id: string}
     */
    private function validateBatchRequest(Request $request): array
    {
        return $request->validate([
            'memo_group_id' => ['required', 'uuid', 'exists:spd_memo_groups,id'],
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['uuid', 'exists:spd_requests,id'],
            'signatory_employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'journal_expense_account_id' => ['required', 'uuid', 'exists:fin_journal_accounts,id'],
            'journal_tax_expense_account_id' => ['required', 'uuid', 'exists:fin_journal_accounts,id'],
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

    private function unpaidGroupsQuery(): Builder
    {
        return DB::table('spd_memo_groups as g')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('spd_requests as r')
                    ->whereColumn('r.memo_group_id', 'g.id')
                    ->where('r.status', 'approved');
            })
            ->select('g.*', DB::raw('(select count(*) from spd_requests r2 where r2.memo_group_id = g.id and r2.status = \'approved\') as jumlah_belum_dibayar'))
            ->orderByDesc('g.memo_date');
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
