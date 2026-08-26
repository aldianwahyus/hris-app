<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Payroll\Application\DecidePayrollRun;
use App\Modules\Payroll\Application\RunPayrollDraftForAllOffices;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Human Capital (hr_approver, lingkup BANK_WIDE) — SATU-SATUNYA pihak
 * yang berwenang men-generate DAN menyetujui payroll (SEC-2026-08:
 * admin cabang/hr_admin TIDAK PERNAH boleh mengajukan payroll
 * kantornya sendiri — rute itu sudah dihapus total dari
 * routes/web.php, bukan sekadar disembunyikan). Larangan menyetujui
 * buatan sendiri ditegakkan di Application/DecidePayrollRun (§6.3) —
 * inilah yang menjaga pemisahan tugas generate vs setuju TETAP ADA
 * meski keduanya kini sama-sama wewenang hr_approver: dua orang
 * berbeda yang sama-sama memegang peran ini tetap wajib terlibat,
 * satu orang tidak bisa generate lalu menyetujui sendiri.
 *
 * generateBulk() adalah SATU-SATUNYA jalur generate payroll di
 * seluruh aplikasi — tidak ada lagi jalur per-kantor oleh hr_admin.
 */
final class PayrollApprovalController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly DecidePayrollRun $decide,
        private readonly RunPayrollDraftForAllOffices $generateAll,
    ) {}

    public function index(): View
    {
        $baseQuery = fn () => DB::table('pay_payroll_runs as r')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->join('emp_employees as maker', 'maker.id', '=', 'r.created_by')
            ->leftJoin('pay_payslips as s', 's.payroll_run_id', '=', 'r.id')
            ->selectRaw(
                'r.id, r.period, r.created_at, o.name as office_name, maker.full_name as maker_name, '
                .'count(s.id) as jumlah_slip, coalesce(sum(s.take_home_partial_cents), 0) as total_take_home_partial'
            )
            ->groupBy('r.id', 'r.period', 'r.created_at', 'o.name', 'maker.full_name');

        $runs = $baseQuery()->where('r.status', 'draft')->orderBy('r.period')->get();

        // Ditampilkan supaya hr_approver bisa membuka kembali (reopen)
        // bila admin cabang perlu mengubah potongan setelah approve —
        // lihat DecidePayrollRun::reopen() & PayrollDeductionController.
        $approvedRuns = $baseQuery()->where('r.status', 'approved')->orderByDesc('r.period')->limit(50)->get();

        // SK Perubahan Gaji yang BELUM disetujui hr_approver — ditampilkan
        // sebagai panel tinjauan sebelum admin men-generate payroll (gaji
        // pegawai ini masih memakai nilai LAMA sampai pengajuannya
        // diputus, lihat RunPayrollDraft yang membaca emp_employees
        // langsung saat generate). Murni informasional (gerbang UX di
        // view), server TIDAK memblokir generate walau daftar ini terisi.
        $pendingSalaryChanges = DB::table('emp_decision_letters as d')
            ->join('emp_profile_change_requests as r', 'r.id', '=', 'd.profile_change_request_id')
            ->join('emp_employees as e', 'e.id', '=', 'd.employee_id')
            ->where('d.sk_type', 'perubahan_gaji')
            ->where('r.status', 'pending')
            ->orderBy('d.sk_date')
            ->get(['d.sk_number', 'd.sk_date', 'e.full_name', 'e.nrp']);

        return view('admin.payroll-approval-queue', compact('runs', 'approvedRuns', 'pendingSalaryChanges'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — bank-wide, tanpa filter AccessPolicy. */
    public function pendingCount(): int
    {
        return DB::table('pay_payroll_runs')->where('status', 'draft')->count();
    }

    public function approve(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, fn (AuditActor $actor) => $this->decide->approve($id, $actor), 'Payroll run disetujui.', $request);
    }

    public function reject(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, fn (AuditActor $actor) => $this->decide->reject($id, $actor), 'Payroll run ditolak.', $request);
    }

    public function reopen(string $id, Request $request): RedirectResponse
    {
        return $this->act(
            $id,
            fn (AuditActor $actor) => $this->decide->reopen($id, $actor),
            'Payroll run dibuka kembali — admin cabang kantor terkait dapat mengubah potongan lagi.',
            $request,
        );
    }

    /**
     * Detail baca-saja satu payroll run — daftar per-pegawai lengkap
     * dengan potongan/tambahan dan total bersih, untuk run BERSTATUS
     * APA PUN (draft atau approved). Bank-wide, tidak dibatasi office
     * (kontroler ini sudah bank-wide-only lewat middleware). BEDA dari
     * PayrollDeductionController::show() (hr_admin, hanya draft, ada
     * form edit) — di sini murni tampilan "gaji final setelah potongan".
     */
    public function show(string $id): View
    {
        $run = DB::table('pay_payroll_runs as r')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->where('r.id', $id)
            ->select('r.id', 'r.period', 'r.status', 'o.name as office_name')
            ->first();

        abort_if($run === null, 404);

        $rows = DB::table('pay_payslips as s')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->where('s.payroll_run_id', $id)
            ->orderBy('e.full_name')
            ->select('s.id as payslip_id', 'e.nrp', 'e.full_name', 's.take_home_partial_cents')
            ->get();

        $deductionsByPayslip = DB::table('pay_payslip_deductions')
            ->whereIn('payslip_id', $rows->pluck('payslip_id'))
            ->get()
            ->groupBy('payslip_id');

        $additionsByPayslip = DB::table('pay_payslip_additions')
            ->whereIn('payslip_id', $rows->pluck('payslip_id'))
            ->get()
            ->groupBy('payslip_id');

        return view('admin.payroll-run-detail', compact('run', 'rows', 'deductionsByPayslip', 'additionsByPayslip'));
    }

    public function closePeriod(Request $request): RedirectResponse
    {
        $validated = $request->validate(['period' => ['required', 'date_format:Y-m']]);

        try {
            $result = $this->decide->approveAllForPeriod(
                period: new DateTimeImmutable($validated['period'].'-01'),
                actor: $this->currentActor($request),
            );
        } catch (DomainException $e) {
            return redirect()->route('admin.payroll-approval-queue')->with('gagal', $e->getMessage());
        }

        $pesan = count($result->approvedOfficeNames) > 0
            ? "Periode {$validated['period']} ditutup untuk: ".implode(', ', $result->approvedOfficeNames).'.'
            : 'Tidak ada draf payroll berstatus menunggu untuk periode ini.';

        return redirect()->route('admin.payroll-approval-queue')->with('sukses', $pesan);
    }

    public function generateBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate(['period' => ['required', 'date_format:Y-m']]);

        try {
            $result = $this->generateAll->handle(
                period: new DateTimeImmutable($validated['period'].'-01'),
                actor: $this->currentActor($request),
            );
        } catch (DomainException|RuntimeException $e) {
            // Mis. ParameterNotFoundException (iuran belum berlaku pada
            // periode yang dipilih) — sengaja fail loudly di domain, tapi
            // di sini diterjemahkan jadi pesan yang bisa ditindaklanjuti
            // alih-alih halaman 500 mentah.
            return redirect()->route('admin.payroll-approval-queue')->with('gagal', $e->getMessage());
        }

        $pesan = count($result->createdOfficeNames) > 0
            ? 'Draf payroll dibuat untuk: '.implode(', ', $result->createdOfficeNames).'.'
            : 'Tidak ada kantor baru yang di-generate.';

        if ($result->skippedAlreadyExistsOfficeNames !== []) {
            $pesan .= ' Dilewati (sudah ada draf periode ini): '.implode(', ', $result->skippedAlreadyExistsOfficeNames).'.';
        }

        return redirect()->route('admin.payroll-approval-queue')->with('sukses', $pesan);
    }

    private function act(string $id, callable $action, string $successMessage, Request $request): RedirectResponse
    {
        try {
            $action($this->currentActor($request));
        } catch (DomainException $e) {
            return redirect()->route('admin.payroll-approval-queue')->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.payroll-approval-queue')->with('sukses', $successMessage);
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
