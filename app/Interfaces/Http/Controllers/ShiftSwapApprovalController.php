<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\AccessPolicy;
use App\Modules\Access\Domain\OrganizationalScope;
use App\Modules\Access\Domain\Role;
use App\Modules\Employee\Contracts\EmployeeRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean persetujuan Tukar Shift — 1 TAHAP, Atasan Langsung SAJA
 * (OFFICE_TREE, berbasis kantor PEMOHON) — sengaja TIDAK 2 tahap seperti
 * Cuti/Lembur/SPPD: tukar shift tidak berdampak finansial/saldo cuti,
 * jadi tidak butuh kontrol berlapis Pimpinan Kantor. Auditor hanya-baca.
 *
 * Penyetuju TIDAK BOLEH menjadi salah satu pihak yang bertukar (pemohon
 * MAUPUN rekan yang dituju) — ditegakkan di sini via perbandingan
 * langsung, bukan lewat AccessPolicy::canApprove (yang hanya mengecek
 * kepemilikan PEMOHON, tidak tahu soal pihak kedua/rekan).
 */
final class ShiftSwapApprovalController extends Controller
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

        $rows = DB::table('shf_swap_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.requesting_employee_id')
            ->join('emp_employees as c', 'c.id', '=', 'r.counterpart_employee_id')
            ->join('shf_shift_patterns as rp', 'rp.id', '=', 'r.requesting_original_pattern_id')
            ->join('shf_shift_patterns as cp', 'cp.id', '=', 'r.counterpart_original_pattern_id')
            ->select(
                'r.id', 'r.request_number', 'r.swap_date', 'r.reason',
                'e.id as employee_id', 'e.office_id', 'e.full_name as requesting_name',
                'c.full_name as counterpart_name',
                'rp.name as requesting_pattern_name', 'cp.name as counterpart_pattern_name',
            )
            ->where('r.status', 'pending')
            ->orderBy('r.swap_date')
            ->get()
            ->filter(fn ($row) => $this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()))
            ->values();

        return view('admin.shift-swap-approval-queue', compact('rows'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — query+filter SAMA seperti index(), hanya count. */
    public function pendingCount(): int
    {
        if (! ($this->actor->hasRole(Role::AtasanLangsung->value) || $this->actor->hasRole(Role::Auditor->value))) {
            return 0;
        }

        return DB::table('shf_swap_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.requesting_employee_id')
            ->select('e.office_id', 'e.id as employee_id')
            ->where('r.status', 'pending')
            ->get()
            ->filter(fn ($row) => $this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()))
            ->count();
    }

    public function approve(string $id): RedirectResponse
    {
        return $this->decide($id, 'approved', 'Pengajuan tukar shift disetujui.');
    }

    public function reject(string $id): RedirectResponse
    {
        return $this->decide($id, 'rejected', 'Pengajuan tukar shift ditolak.');
    }

    private function decide(string $id, string $status, string $successMessage): RedirectResponse
    {
        $request = DB::table('shf_swap_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.requesting_employee_id')
            ->select('r.id', 'r.status', 'r.requesting_employee_id', 'r.counterpart_employee_id', 'e.id as employee_id', 'e.office_id')
            ->where('r.id', $id)
            ->first();

        abort_if($request === null, 404);

        $policy = $this->policy();
        $actorEmployeeId = $this->actor->employeeId();

        $canSeeIt = $policy->canAccessRecord($request->office_id, $request->employee_id, $actorEmployeeId);
        $canApproveIt = $policy->canApprove($request->employee_id, $actorEmployeeId);
        $isCounterpart = $actorEmployeeId !== null && $actorEmployeeId === $request->counterpart_employee_id;

        abort_unless($canSeeIt && $canApproveIt && ! $isCounterpart, 403, 'Pengajuan ini di luar lingkup kewenangan Anda.');

        $affected = DB::table('shf_swap_requests')
            ->where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'approver_id' => $actorEmployeeId,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.shift-swap-queue')
            ->with($affected > 0 ? 'sukses' : 'gagal', $affected > 0
                ? $successMessage
                : 'Pengajuan sudah diputus sebelumnya.');
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
