<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\AccessPolicy;
use App\Modules\Access\Domain\OrganizationalScope;
use App\Modules\Access\Domain\Role;
use App\Modules\Attendance\Application\DecideOutsideAttendanceRequest;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean persetujuan Absen Luar Kantor — 1 TAHAP, Pimpinan Kantor SAJA
 * (OFFICE EXACT, bukan office-tree — persis kantor pemohon). Role
 * `pimpinan_kantor` yang sama dipakai baik untuk Pimpinan Unit/Divisi
 * di kantor pusat maupun Kepala KC/KCP di cabang — dibedakan hanya dari
 * office_type kantornya, bukan role terpisah. Auditor hanya-baca,
 * bank-wide.
 */
final class OutsideAttendanceApprovalController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly DecideOutsideAttendanceRequest $decide,
    ) {}

    public function index(): View
    {
        $isPimpinanKantor = $this->actor->hasRole(Role::PimpinanKantor->value);
        $isAuditor = $this->actor->hasRole(Role::Auditor->value);

        abort_unless($isPimpinanKantor || $isAuditor, 403, 'Anda tidak memiliki peran yang berwenang melihat antrean ini.');

        $rows = DB::table('att_outside_attendance_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select(
                'r.id', 'r.request_number', 'r.work_date', 'r.reason',
                'e.id as employee_id', 'e.office_id', 'e.full_name as requesting_name', 'e.nrp',
            )
            ->where('r.status', 'pending')
            ->orderBy('r.work_date')
            ->get()
            ->filter(fn ($row) => $this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()))
            ->values();

        return view('admin.outside-attendance-approval-queue', compact('rows'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — query+filter SAMA seperti index(), hanya count. */
    public function pendingCount(): int
    {
        if (! ($this->actor->hasRole(Role::PimpinanKantor->value) || $this->actor->hasRole(Role::Auditor->value))) {
            return 0;
        }

        return DB::table('att_outside_attendance_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select('e.office_id', 'e.id as employee_id')
            ->where('r.status', 'pending')
            ->get()
            ->filter(fn ($row) => $this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()))
            ->count();
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        return $this->decideRequest($request, $id, 'approve', 'Pengajuan absen luar kantor disetujui.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        return $this->decideRequest($request, $id, 'reject', 'Pengajuan absen luar kantor ditolak.');
    }

    private function decideRequest(Request $request, string $id, string $action, string $successMessage): RedirectResponse
    {
        $outsideRequest = DB::table('att_outside_attendance_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select('r.id', 'r.status', 'e.id as employee_id', 'e.office_id')
            ->where('r.id', $id)
            ->first();

        abort_if($outsideRequest === null, 404);

        $policy = $this->policy();
        $actorEmployeeId = $this->actor->employeeId();

        $canSeeIt = $policy->canAccessRecord($outsideRequest->office_id, $outsideRequest->employee_id, $actorEmployeeId);
        $canApproveIt = $policy->canApprove($outsideRequest->employee_id, $actorEmployeeId);

        abort_unless($canSeeIt && $canApproveIt, 403, 'Pengajuan ini di luar lingkup kewenangan Anda.');

        if ($outsideRequest->status !== 'pending') {
            return redirect()->route('admin.outside-attendance-queue')->with('gagal', 'Pengajuan sudah diputus sebelumnya.');
        }

        $actorInfo = new AuditActor(
            actorId: $actorEmployeeId,
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->decide->{$action}($id, (string) $actorEmployeeId, $actorInfo);

        return redirect()->route('admin.outside-attendance-queue')->with('sukses', $successMessage);
    }

    private function policy(): AccessPolicy
    {
        if ($this->actor->hasRole(Role::Auditor->value)) {
            return new AccessPolicy(Role::Auditor, OrganizationalScope::bankWide());
        }

        $ownOfficeId = $this->actor->officeId();

        return new AccessPolicy(Role::PimpinanKantor, OrganizationalScope::office($ownOfficeId ?? ''));
    }
}
