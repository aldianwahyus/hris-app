<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\DecideEmployeeProfileChange;
use App\Modules\Employee\Application\DecideNewEmployeeRequest;
use App\Modules\Onboarding\Application\GenerateOnboardingChecklist;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * hr_approver (checker, lingkup BANK_WIDE) — menyetujui/menolak
 * pengajuan perubahan data induk pegawai dari hr_admin (maker) di
 * kantor mana pun. Larangan menyetujui pengajuan sendiri ditegakkan
 * di sini karena, tidak seperti Payroll/Cuti, hr_approver TIDAK
 * memiliki lingkup kantor sendiri untuk dibandingkan (BANK_WIDE) —
 * satu-satunya pembanding valid adalah requested_by.
 */
final class EmployeeApprovalQueueController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly DecideEmployeeProfileChange $decide,
        private readonly DecideNewEmployeeRequest $decideNew,
        private readonly GenerateOnboardingChecklist $generateOnboarding,
    ) {}

    public function index(): View
    {
        $rows = DB::table('emp_profile_change_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('emp_employees as maker', 'maker.id', '=', 'r.requested_by')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->select(
                'r.id', 'r.proposed_changes', 'r.previous_values', 'r.created_at',
                'e.full_name', 'e.nrp', 'o.name as office_name',
                'maker.full_name as maker_name',
            )
            ->where('r.status', 'pending')
            ->orderBy('r.created_at')
            ->get()
            ->map(function ($row) {
                $row->proposed_changes = json_decode($row->proposed_changes, true);
                $row->previous_values = json_decode($row->previous_values, true);

                return $row;
            });

        // Usulan PEGAWAI BARU (SYSADMIN, maker) — tabel terpisah dari
        // revisi data pegawai di atas (tidak ada employee_id sampai
        // disetujui), lihat SubmitNewEmployeeRequest/DecideNewEmployeeRequest.
        $newEmployeeRows = DB::table('emp_new_employee_requests as r')
            ->join('emp_employees as maker', 'maker.id', '=', 'r.requested_by')
            ->select('r.id', 'r.proposed_data', 'r.created_at', 'maker.full_name as maker_name')
            ->where('r.status', 'pending')
            ->orderBy('r.created_at')
            ->get()
            ->map(function ($row) {
                $row->proposed_data = json_decode($row->proposed_data, true);

                return $row;
            });

        return view('admin.employee-approval-queue', compact('rows', 'newEmployeeRows'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — bank-wide, tanpa filter AccessPolicy (gerbang cukup permission rute). */
    public function pendingCount(): int
    {
        return DB::table('emp_profile_change_requests')->where('status', 'pending')->count()
            + DB::table('emp_new_employee_requests')->where('status', 'pending')->count();
    }

    public function approve(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, fn (AuditActor $actor) => $this->decide->approve($id, $actor), 'Perubahan data pegawai disetujui.', $request);
    }

    public function reject(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, fn (AuditActor $actor) => $this->decide->reject($id, $actor), 'Perubahan data pegawai ditolak.', $request);
    }

    public function approveNewEmployee(string $id, Request $request): RedirectResponse
    {
        $actor = $this->currentActor($request);

        try {
            $newPassword = $this->decideNew->approve($id, $actor);
        } catch (DomainException $e) {
            return redirect()->route('admin.employee-approval-queue')->with('gagal', $e->getMessage());
        }

        // Onboarding Terstruktur — modul baru (evaluasi PM/client
        // 2026-09-02), dipicu di sini (BUKAN di dalam transaksi
        // DecideNewEmployeeRequest) pola PERSIS
        // triggerBekalCutiIfFirstThisYear pada Cuti — modul Employee
        // tidak boleh mengenal modul Onboarding (ModuleBoundaryTest M-1).
        $employeeId = DB::table('emp_new_employee_requests')->where('id', $id)->value('created_employee_id');

        if ($employeeId !== null) {
            $employmentStatus = DB::table('emp_employees')->where('id', $employeeId)->value('employment_status');
            $this->generateOnboarding->handle($employeeId, $employmentStatus, $actor);
        }

        return redirect()->route('admin.employee-approval-queue')
            ->with('sukses', 'Pegawai baru dibuat beserta akun login-nya.')
            ->with('kata_sandi_baru', $newPassword)
            ->with('kata_sandi_untuk', 'pegawai baru');
    }

    public function rejectNewEmployee(string $id, Request $request): RedirectResponse
    {
        try {
            $this->decideNew->reject($id, $this->currentActor($request));
        } catch (DomainException $e) {
            return redirect()->route('admin.employee-approval-queue')->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.employee-approval-queue')->with('sukses', 'Pengajuan pegawai baru ditolak.');
    }

    private function act(string $id, callable $action, string $successMessage, Request $request): RedirectResponse
    {
        $requestedBy = DB::table('emp_profile_change_requests')->where('id', $id)->value('requested_by');

        abort_unless($requestedBy !== $this->actor->employeeId(), 403, 'Tidak dapat memutuskan pengajuan milik sendiri (§6.3).');

        try {
            $action($this->currentActor($request));
        } catch (DomainException|RuntimeException $e) {
            return redirect()->route('admin.employee-approval-queue')->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.employee-approval-queue')->with('sukses', $successMessage);
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
