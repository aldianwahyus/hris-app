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
 * Antrean persetujuan lembur — 2 TAHAP (koreksi atas DEC-92 versi awal
 * yang memotong hierarki langsung ke Direktur Bidang/Pembina):
 *
 *  1. Atasan Langsung (status='pending', lingkup OFFICE_TREE — pola
 *     PERSIS Cuti, lihat LeaveApprovalQueueController) memutus dulu.
 *  2. Setuju di tahap 1 → naik ke Pimpinan Kantor (status=
 *     'pending_pimpinan', lingkup OFFICE persis — kepala unit/divisi
 *     pemohon di Kantor Pusat, atau kepala KC/KCP pemohon di cabang).
 *     Tolak di tahap 1 → SELESAI (status='rejected'), tidak naik.
 *
 * DirekturBidang/DirekturPembina TIDAK LAGI dipakai di sini (role
 * tetap ada di enum Role, hanya berhenti dipakai untuk Lembur).
 * Auditor tetap melihat KEDUA tahap secara bank-wide, hanya-baca.
 *
 * `approver_id`/`decided_at` (kolom lama) tetap berarti "keputusan
 * FINAL" — dibaca apa adanya oleh SPKL PDF — sekarang diisi keputusan
 * tahap 2 (atau tahap 1 bila ditolak di situ). Kolom baru
 * `atasan_approver_id`/`atasan_decided_at` HANYA jejak tahap 1.
 *
 * Guard swa-putus LINTAS TAHAP: orang yang sama tidak boleh memutus
 * tahap 1 lalu tahap 2 untuk pengajuan yang SAMA (bisa terjadi di
 * kantor kecil bila satu orang memegang AtasanLangsung DAN
 * PimpinanKantor sekaligus) — cermin larangan swa-putus §6.3.
 */
final class ApprovalQueueController extends Controller
{
    private const CRITICAL_DAYS = 3;

    private const WARNING_DAYS = 14;

    public function __construct(
        private readonly CurrentActor $actor,
        private readonly EmployeeRepository $employees,
    ) {}

    public function index(): View
    {
        $this->assertHasAnyRelevantRole();

        $today = new \DateTimeImmutable('today');

        $rows = DB::table('ovt_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->select(
                'r.id', 'r.spkl_number', 'r.overtime_type', 'r.work_date', 'r.status',
                'r.payable_hours', 'r.amount_cents', 'r.approval_deadline',
                'e.id as employee_id', 'e.office_id', 'e.full_name', 'e.person_grade',
                'p.name as position_name',
                'o.name as office_name'
            )
            ->whereIn('r.status', ['pending', 'pending_pimpinan'])
            ->orderBy('r.approval_deadline')
            ->get()
            ->filter(function ($row) {
                $policy = $this->policyForTier($this->tierFor($row->status));

                return $policy !== null
                    && $policy->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId());
            })
            ->map(function ($row) use ($today) {
                $deadline = new \DateTimeImmutable($row->approval_deadline);
                $remaining = (int) $today->diff($deadline)->format('%r%a');

                $row->remaining_days = $remaining;
                $row->urgency = match (true) {
                    $remaining <= self::CRITICAL_DAYS => 'kritis',
                    $remaining <= self::WARNING_DAYS => 'hati',
                    default => 'aman',
                };
                $row->tahap = $row->status === 'pending' ? 'Atasan Langsung' : 'Pimpinan Kantor';

                return $row;
            })
            ->values();

        $summary = [
            'menunggu' => $rows->count(),
            'kritis' => $rows->where('urgency', 'kritis')->count(),
            'jam' => $rows->where('overtime_type', '!=', 'crash_program')->sum('payable_hours'),
            'nilai' => $rows->sum('amount_cents'),
        ];

        return view('admin.approval-queue', compact('rows', 'summary'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — query+filter SAMA seperti index(), hanya count. */
    public function pendingCount(): int
    {
        if (! ($this->actor->hasRole(Role::AtasanLangsung->value) || $this->actor->hasRole(Role::PimpinanKantor->value) || $this->actor->hasRole(Role::Auditor->value))) {
            return 0;
        }

        return DB::table('ovt_requests as r')
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
        return $this->decide($id, 'approve', 'Pengajuan lembur disetujui.');
    }

    public function reject(string $id): RedirectResponse
    {
        return $this->decide($id, 'reject', 'Pengajuan lembur ditolak.');
    }

    private function decide(string $id, string $decision, string $successMessage): RedirectResponse
    {
        $request = DB::table('ovt_requests as r')
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

        $affected = DB::table('ovt_requests')
            ->where('id', $id)
            ->where('status', $request->status)
            ->update($updates);

        return redirect()
            ->route('admin.approval-queue')
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
