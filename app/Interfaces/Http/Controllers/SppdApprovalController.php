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
 * Antrean persetujuan SPPD (BPP/442/03/64/2026 §III.A.10, §III.B.1) —
 * SEKARANG 2 TAHAP SERAGAM untuk SEMUA trip_category (koreksi atas
 * pemilahan lama per kategori — Jarak Pendek dulu khusus Atasan
 * Langsung, kategori lain langsung Pejabat SDM). Pola PERSIS
 * LeaveApprovalQueueController/ApprovalQueueController: Atasan Langsung
 * dulu (status 'pending', OFFICE_TREE), baru Pimpinan Kantor (status
 * 'pending_pimpinan', OFFICE persis). hr_approver DIHAPUS dari jalur
 * KEPUTUSAN SPPD (tetap dipakai untuk hal lain: checker data pegawai,
 * payroll, pencairan bank-wide).
 *
 * `approver_id`/`decided_at` (kolom lama) tetap berarti "keputusan
 * FINAL". Kolom `atasan_approver_id`/`atasan_decided_at` HANYA jejak
 * tahap 1.
 */
final class SppdApprovalController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly EmployeeRepository $employees,
    ) {}

    public function index(): View
    {
        $this->assertHasAnyRelevantRole();

        $rows = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->select(
                'r.id', 'r.request_number', 'r.trip_category', 'r.destination', 'r.start_date', 'r.end_date',
                'r.total_days', 'r.currency', 'r.uang_makan_cents', 'r.uang_saku_cents', 'r.status',
                'r.estimasi_hotel_cents', 'r.estimasi_angkutan_setempat_cents', 'r.estimasi_transportasi_tujuan_cents',
                'e.id as employee_id', 'e.office_id', 'e.full_name', 'e.person_grade',
                'o.name as office_name'
            )
            ->whereIn('r.status', ['pending', 'pending_pimpinan'])
            ->orderBy('r.start_date')
            ->get()
            ->filter(function ($row) {
                $policy = $this->policyForTier($this->tierFor($row->status));

                return $policy !== null
                    && $policy->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId());
            })
            ->map(function ($row) {
                $row->tahap = $row->status === 'pending' ? 'Atasan Langsung' : 'Pimpinan Kantor';

                return $row;
            })
            ->values();

        return view('admin.sppd-approval-queue', compact('rows'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — query+filter SAMA seperti index(), hanya count. */
    public function pendingCount(): int
    {
        if (! ($this->actor->hasRole(Role::AtasanLangsung->value) || $this->actor->hasRole(Role::PimpinanKantor->value) || $this->actor->hasRole(Role::Auditor->value))) {
            return 0;
        }

        return DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select('r.status', 'e.office_id', 'e.id as employee_id')
            ->whereIn('r.status', ['pending', 'pending_pimpinan'])
            ->get()
            ->filter(function ($row) {
                $policy = $this->policyForTier($this->tierFor($row->status));

                return $policy !== null && $policy->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId());
            })
            ->count();
    }

    public function approve(string $id): RedirectResponse
    {
        return $this->decide($id, 'approve', 'Pengajuan SPPD disetujui.');
    }

    public function reject(string $id): RedirectResponse
    {
        return $this->decide($id, 'reject', 'Pengajuan SPPD ditolak.');
    }

    private function decide(string $id, string $decision, string $successMessage): RedirectResponse
    {
        $request = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select('r.id', 'r.status', 'r.atasan_approver_id', 'e.id as employee_id', 'e.office_id')
            ->where('r.id', $id)
            ->first();

        abort_if($request === null, 404);

        $tier = $this->tierFor($request->status);

        abort_if($tier === null, 404, 'Pengajuan sudah diputus atau tidak berada pada tahap yang bisa diputuskan.');

        $policy = $this->policyForTier($tier);

        abort_if($policy === null, 403, 'Anda tidak memiliki peran yang berwenang pada tahap pengajuan ini.');

        $canSeeIt = $policy->canAccessRecord($request->office_id, $request->employee_id, $this->actor->employeeId());
        $canApproveIt = $policy->canApprove($request->employee_id, $this->actor->employeeId());

        abort_unless($canSeeIt && $canApproveIt, 403, 'Pengajuan ini di luar lingkup kewenangan Anda.');

        if ($tier === 'pimpinan' && $request->atasan_approver_id === $this->actor->employeeId()) {
            abort(403, 'Anda sudah memutuskan tahap Atasan Langsung untuk pengajuan ini — tidak dapat memutuskan tahap Pimpinan Kantor juga (§6.3).');
        }

        $now = now();
        $updates = ['updated_at' => $now];

        if ($decision === 'reject') {
            $updates += ['status' => 'rejected', 'approver_id' => $this->actor->employeeId(), 'decided_at' => $now];
        } elseif ($tier === 'atasan') {
            $updates += ['status' => 'pending_pimpinan', 'atasan_approver_id' => $this->actor->employeeId(), 'atasan_decided_at' => $now];
        } else {
            $updates += ['status' => 'approved', 'approver_id' => $this->actor->employeeId(), 'decided_at' => $now];
        }

        $affected = DB::table('spd_requests')
            ->where('id', $id)
            ->where('status', $request->status)
            ->update($updates);

        return redirect()
            ->route('admin.sppd-approval-queue')
            ->with($affected > 0 ? 'sukses' : 'gagal', $affected > 0
                ? $successMessage
                : 'Pengajuan sudah diputus sebelumnya.');
    }

    /** @return 'atasan'|'pimpinan'|null */
    private function tierFor(string $status): ?string
    {
        return match ($status) {
            'pending' => 'atasan',
            'pending_pimpinan' => 'pimpinan',
            default => null,
        };
    }

    private function policyForTier(?string $tier): ?AccessPolicy
    {
        if ($tier === null) {
            return null;
        }

        if ($this->actor->hasRole(Role::Auditor->value)) {
            return new AccessPolicy(Role::Auditor, OrganizationalScope::bankWide());
        }

        if ($tier === 'atasan' && $this->actor->hasRole(Role::AtasanLangsung->value)) {
            $ownOfficeId = $this->actor->officeId();
            $officeIds = $ownOfficeId === null ? [] : $this->employees->officeIdsInTree($ownOfficeId);

            return new AccessPolicy(Role::AtasanLangsung, OrganizationalScope::officeTree($officeIds));
        }

        if ($tier === 'pimpinan' && $this->actor->hasRole(Role::PimpinanKantor->value)) {
            $ownOfficeId = $this->actor->officeId();

            if ($ownOfficeId === null) {
                return null;
            }

            return new AccessPolicy(Role::PimpinanKantor, OrganizationalScope::office($ownOfficeId));
        }

        return null;
    }

    private function assertHasAnyRelevantRole(): void
    {
        abort_unless(
            $this->actor->hasRole(Role::AtasanLangsung->value)
                || $this->actor->hasRole(Role::PimpinanKantor->value)
                || $this->actor->hasRole(Role::Auditor->value),
            403,
            'Anda tidak memiliki peran yang berwenang melihat antrean ini.'
        );
    }
}
