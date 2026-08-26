<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Payroll\Application\GeneratePayslipDeductionTemplate;
use App\Modules\Payroll\Application\ImportPayslipDeductions;
use App\Modules\Payroll\Application\RecordPayslipAddition;
use App\Modules\Payroll\Application\RecordPayslipDeduction;
use App\Modules\Payroll\Application\RemovePayslipAddition;
use App\Modules\Payroll\Application\RemovePayslipDeduction;
use App\Modules\Payroll\Domain\AdditionType;
use App\Modules\Payroll\Domain\DeductionType;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use ValueError;

/**
 * Input potongan gaji oleh admin cabang (hr_admin, maker) — SELAMA
 * payroll run kantornya berstatus draft. SEC-2026-08 melarang admin
 * cabang generate/melihat payroll SAMA SEKALI — kontroler ini BUKAN
 * pengecualian dari itu, melainkan wewenang berbeda dan lebih sempit:
 * input DATA pada draf yang SUDAH dibuat Human Capital (hr_approver
 * lewat generate massal), bukan wewenang generate.
 *
 * Setiap aksi digerbangi run.office_id === kantor sendiri DAN
 * run.status === 'draft' — 404 kalau tidak (pola sama SEC-2026-08:
 * tidak membocorkan keberadaan run kantor lain/yang sudah approved,
 * bukan sekadar 403). Begitu Pejabat SDM meng-APPROVE run (lihat
 * PayrollApprovalController), akses ini TERTUTUP TOTAL — hanya bisa
 * dibuka kembali oleh hr_approver (DecidePayrollRun::reopen()).
 */
final class PayrollDeductionController
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly RecordPayslipDeduction $record,
        private readonly RemovePayslipDeduction $remove,
        private readonly RecordPayslipAddition $recordAddition,
        private readonly RemovePayslipAddition $removeAddition,
        private readonly ImportPayslipDeductions $import,
        private readonly GeneratePayslipDeductionTemplate $template,
    ) {}

    public function index(): View
    {
        $officeId = $this->actor->officeId();

        $runs = $officeId === null ? collect() : DB::table('pay_payroll_runs')
            ->where('office_id', $officeId)
            ->where('status', 'draft')
            ->orderByDesc('period')
            ->get(['id', 'period', 'created_at']);

        return view('payroll.deduction-index', compact('runs'));
    }

    public function show(string $runId): View
    {
        $run = $this->draftRunInOwnOffice($runId);

        $rows = DB::table('pay_payslips as s')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->where('s.payroll_run_id', $runId)
            ->orderBy('e.full_name')
            ->select('s.id as payslip_id', 'e.nrp', 'e.full_name', 's.take_home_partial_cents')
            ->get();

        $deductionsByPayslip = DB::table('pay_payslip_deductions')
            ->whereIn('payslip_id', $rows->pluck('payslip_id'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('payslip_id');

        $additionsByPayslip = DB::table('pay_payslip_additions')
            ->whereIn('payslip_id', $rows->pluck('payslip_id'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('payslip_id');

        $deductionTypes = DeductionType::cases();
        $additionTypes = AdditionType::cases();

        return view('payroll.deduction-show', [
            'run' => $run,
            'rows' => $rows,
            'deductionsByPayslip' => $deductionsByPayslip,
            'deductionTypes' => $deductionTypes,
            'additionsByPayslip' => $additionsByPayslip,
            'additionTypes' => $additionTypes,
        ]);
    }

    public function store(string $runId, string $payslipId, Request $request): RedirectResponse
    {
        $this->draftRunInOwnOffice($runId);
        $this->payslipInRun($runId, $payslipId);

        $validated = $request->validate([
            'deduction_type' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $type = DeductionType::from($validated['deduction_type']);
        } catch (ValueError) {
            return back()->with('gagal', 'Jenis potongan tidak dikenali.');
        }

        try {
            $this->record->handle(
                payslipId: $payslipId,
                type: $type,
                amountCents: ((int) $validated['amount']) * 100,
                note: $validated['note'] ?? null,
                actor: $this->currentActor($request),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('hr.payroll-deduction.show', $runId)->with('sukses', 'Potongan tersimpan.');
    }

    public function destroy(string $runId, string $payslipId, string $deductionId, Request $request): RedirectResponse
    {
        $this->draftRunInOwnOffice($runId);
        $this->payslipInRun($runId, $payslipId);

        abort_unless(
            DB::table('pay_payslip_deductions')->where('id', $deductionId)->where('payslip_id', $payslipId)->exists(),
            404
        );

        try {
            $this->remove->handle($deductionId, $this->currentActor($request));
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('hr.payroll-deduction.show', $runId)->with('sukses', 'Potongan dihapus.');
    }

    public function storeAddition(string $runId, string $payslipId, Request $request): RedirectResponse
    {
        $this->draftRunInOwnOffice($runId);
        $this->payslipInRun($runId, $payslipId);

        $validated = $request->validate([
            'addition_type' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $type = AdditionType::from($validated['addition_type']);
        } catch (ValueError) {
            return back()->with('gagal', 'Jenis tambahan penghasilan tidak dikenali.');
        }

        try {
            $this->recordAddition->handle(
                payslipId: $payslipId,
                type: $type,
                amountCents: ((int) $validated['amount']) * 100,
                note: $validated['note'] ?? null,
                actor: $this->currentActor($request),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('hr.payroll-deduction.show', $runId)->with('sukses', 'Tambahan penghasilan tersimpan.');
    }

    public function destroyAddition(string $runId, string $payslipId, string $additionId, Request $request): RedirectResponse
    {
        $this->draftRunInOwnOffice($runId);
        $this->payslipInRun($runId, $payslipId);

        abort_unless(
            DB::table('pay_payslip_additions')->where('id', $additionId)->where('payslip_id', $payslipId)->exists(),
            404
        );

        try {
            $this->removeAddition->handle($additionId, $this->currentActor($request));
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('hr.payroll-deduction.show', $runId)->with('sukses', 'Tambahan penghasilan dihapus.');
    }

    public function template(string $runId): Response
    {
        $this->draftRunInOwnOffice($runId);

        $csv = $this->template->handle($runId);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"template-potongan-{$runId}.csv\"",
        ]);
    }

    public function importAction(string $runId, Request $request): RedirectResponse
    {
        $this->draftRunInOwnOffice($runId);

        $validated = $request->validate([
            'berkas' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $realPath = $validated['berkas']->getRealPath();

        abort_if($realPath === false || $realPath === null, 422, 'Berkas gagal diunggah.');

        try {
            $result = $this->import->handle($runId, $realPath, $this->currentActor($request));
        } catch (RuntimeException $e) {
            return redirect()->route('hr.payroll-deduction.show', $runId)->with('gagal', $e->getMessage());
        }

        $pesan = "Impor selesai: {$result->imported} baris diproses, {$result->skipped} baris dilewati.";

        if ($result->skipped > 0) {
            $pesan .= ' Rincian baris dilewati: '.implode(' ', array_slice($result->skippedReasons, 0, 5));
        }

        return redirect()->route('hr.payroll-deduction.show', $runId)->with('sukses', $pesan);
    }

    private function draftRunInOwnOffice(string $runId): object
    {
        $officeId = $this->actor->officeId();

        $run = $officeId === null ? null : DB::table('pay_payroll_runs')
            ->where('id', $runId)
            ->where('office_id', $officeId)
            ->where('status', 'draft')
            ->first();

        abort_if($run === null, 404);

        return $run;
    }

    private function payslipInRun(string $runId, string $payslipId): void
    {
        abort_unless(
            DB::table('pay_payslips')->where('id', $payslipId)->where('payroll_run_id', $runId)->exists(),
            404
        );
    }

    private function currentActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
