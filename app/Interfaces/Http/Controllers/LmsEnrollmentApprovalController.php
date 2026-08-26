<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\AccessPolicy;
use App\Modules\Access\Domain\OrganizationalScope;
use App\Modules\Access\Domain\Role;
use App\Modules\Employee\Contracts\EmployeeRepository;
use App\Modules\Lms\Application\DecideEnrollment;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean persetujuan pendaftaran pelatihan — 1 TAHAP, Atasan Langsung
 * SAJA (OFFICE_TREE, berbasis kantor pemohon) — pola PERSIS
 * ShiftSwapApprovalController/OutsideAttendanceApprovalController:
 * pelatihan tidak berdampak finansial/saldo cuti langsung, tidak butuh
 * kontrol berlapis seperti Cuti/Lembur/SPPD. Auditor hanya-baca,
 * bank-wide.
 */
final class LmsEnrollmentApprovalController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly EmployeeRepository $employees,
        private readonly DecideEnrollment $decide,
    ) {}

    public function index(): View
    {
        $isAtasanLangsung = $this->actor->hasRole(Role::AtasanLangsung->value);
        $isAuditor = $this->actor->hasRole(Role::Auditor->value);

        abort_unless($isAtasanLangsung || $isAuditor, 403, 'Anda tidak memiliki peran yang berwenang melihat antrean ini.');

        $rows = DB::table('lms_enrollments as en')
            ->join('emp_employees as e', 'e.id', '=', 'en.employee_id')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->select(
                'en.id', 'en.enrollment_number', 'en.requested_at',
                'e.id as employee_id', 'e.office_id', 'e.full_name as employee_name', 'e.nrp',
                'c.title as course_title', 'b.batch_code', 'b.start_date', 'b.end_date',
            )
            ->where('en.status', 'pending')
            ->orderBy('en.requested_at')
            ->get()
            ->filter(fn ($row) => $this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()))
            ->values();

        return view('admin.lms-enrollment-approval-queue', compact('rows'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — query+filter SAMA seperti index(), hanya count. */
    public function pendingCount(): int
    {
        if (! ($this->actor->hasRole(Role::AtasanLangsung->value) || $this->actor->hasRole(Role::Auditor->value))) {
            return 0;
        }

        return DB::table('lms_enrollments as en')
            ->join('emp_employees as e', 'e.id', '=', 'en.employee_id')
            ->select('e.office_id', 'e.id as employee_id')
            ->where('en.status', 'pending')
            ->get()
            ->filter(fn ($row) => $this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()))
            ->count();
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        return $this->decideEnrollment($request, $id, 'approve', 'Pendaftaran pelatihan disetujui.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        return $this->decideEnrollment($request, $id, 'reject', 'Pendaftaran pelatihan ditolak.');
    }

    private function decideEnrollment(Request $request, string $id, string $action, string $successMessage): RedirectResponse
    {
        $enrollment = DB::table('lms_enrollments as en')
            ->join('emp_employees as e', 'e.id', '=', 'en.employee_id')
            ->select('en.id', 'en.status', 'e.id as employee_id', 'e.office_id')
            ->where('en.id', $id)
            ->first();

        abort_if($enrollment === null, 404);

        $policy = $this->policy();
        $actorEmployeeId = $this->actor->employeeId();

        $canSeeIt = $policy->canAccessRecord($enrollment->office_id, $enrollment->employee_id, $actorEmployeeId);
        $canApproveIt = $policy->canApprove($enrollment->employee_id, $actorEmployeeId);

        abort_unless($canSeeIt && $canApproveIt, 403, 'Pendaftaran ini di luar lingkup kewenangan Anda.');

        if ($enrollment->status !== 'pending') {
            return redirect()->route('admin.lms-enrollment-queue')->with('gagal', 'Pendaftaran sudah diputus sebelumnya.');
        }

        $actorInfo = new AuditActor(
            actorId: $actorEmployeeId,
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->decide->{$action}($id, (string) $actorEmployeeId, $actorInfo);

        return redirect()->route('admin.lms-enrollment-queue')->with('sukses', $successMessage);
    }

    private function policy(): AccessPolicy
    {
        if ($this->actor->hasRole(Role::Auditor->value)) {
            return new AccessPolicy(Role::Auditor, OrganizationalScope::bankWide());
        }

        $ownOfficeId = $this->actor->officeId();
        $officeIds = $ownOfficeId === null ? [] : $this->employees->officeIdsInTree($ownOfficeId);

        return new AccessPolicy(Role::AtasanLangsung, OrganizationalScope::officeTree($officeIds));
    }
}
