<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\AccessPolicy;
use App\Modules\Access\Domain\OrganizationalScope;
use App\Modules\Access\Domain\Role;
use App\Modules\Employee\Contracts\EmployeeRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Antrean persetujuan Izin Tidak Masuk Bekerja — 1 TAHAP, Atasan
 * Langsung SAJA (OFFICE_TREE, berbasis kantor PEMOHON) — pola SAMA
 * PERSIS ShiftSwapApprovalController: tidak berdampak finansial/saldo
 * cuti, jadi tidak butuh kontrol berlapis Pimpinan Kantor. Auditor
 * hanya-baca.
 */
final class IzinApprovalController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly EmployeeRepository $employees,
    ) {}

    public function index(): View
    {
        $isAtasanLangsung = $this->actor->hasRole(Role::AtasanLangsung->value);
        $isAuditor = $this->actor->hasRole(Role::Auditor->value);

        abort_unless($isAtasanLangsung || $isAuditor, 403, 'Anda tidak memiliki peran yang berwenang melihat antrean ini.');

        $rows = $this->baseQuery()
            ->where('r.status', 'pending')
            ->orderBy('r.start_date')
            ->get()
            ->filter(fn ($row) => $this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()))
            ->values();

        return view('admin.izin-approval-queue', compact('rows'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — query+filter SAMA seperti index(), hanya count. */
    public function pendingCount(): int
    {
        if (! ($this->actor->hasRole(Role::AtasanLangsung->value) || $this->actor->hasRole(Role::Auditor->value))) {
            return 0;
        }

        return DB::table('izin_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select('e.office_id', 'e.id as employee_id')
            ->where('r.status', 'pending')
            ->get()
            ->filter(fn ($row) => $this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()))
            ->count();
    }

    public function approve(string $id): RedirectResponse
    {
        return $this->decide($id, 'approved', 'Pengajuan izin disetujui.');
    }

    public function reject(string $id): RedirectResponse
    {
        return $this->decide($id, 'rejected', 'Pengajuan izin ditolak.');
    }

    public function downloadAttachment(string $id): StreamedResponse
    {
        $row = $this->baseQuery()->where('r.id', $id)->first();

        abort_if($row === null || $row->attachment_path === null, 404);

        abort_unless($this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()), 403);

        return Storage::disk('s3')->download($row->attachment_path, $row->attachment_original_name);
    }

    private function decide(string $id, string $status, string $successMessage): RedirectResponse
    {
        $request = DB::table('izin_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select('r.id', 'r.status', 'r.employee_id', 'e.office_id')
            ->where('r.id', $id)
            ->first();

        abort_if($request === null, 404);

        $policy = $this->policy();
        $actorEmployeeId = $this->actor->employeeId();

        $canSeeIt = $policy->canAccessRecord($request->office_id, $request->employee_id, $actorEmployeeId);
        $canApproveIt = $policy->canApprove($request->employee_id, $actorEmployeeId);

        abort_unless($canSeeIt && $canApproveIt, 403, 'Pengajuan ini di luar lingkup kewenangan Anda.');

        $affected = DB::table('izin_requests')
            ->where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'approver_id' => $actorEmployeeId,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.izin-queue')
            ->with($affected > 0 ? 'sukses' : 'gagal', $affected > 0
                ? $successMessage
                : 'Pengajuan sudah diputus sebelumnya.');
    }

    private function baseQuery(): Builder
    {
        return DB::table('izin_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select(
                'r.id', 'r.request_number', 'r.category', 'r.start_date', 'r.end_date',
                'r.total_days', 'r.reason', 'r.attachment_path', 'r.attachment_original_name',
                'e.id as employee_id', 'e.office_id', 'e.full_name',
            );
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
