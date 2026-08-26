<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\AccessPolicy;
use App\Modules\Access\Domain\OrganizationalScope;
use App\Modules\Access\Domain\Role;
use App\Modules\Employee\Contracts\EmployeeRepository;
use App\Modules\Leave\Application\ReleaseLeaveBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean persetujuan Cuti — 2 TAHAP, pola PERSIS
 * ApprovalQueueController (Lembur): Atasan Langsung (status='pending',
 * OFFICE_TREE) dulu, baru Pimpinan Kantor (status='pending_pimpinan',
 * OFFICE persis). hr_approver DIHAPUS dari jalur KEPUTUSAN Cuti (tetap
 * dipakai untuk hal lain: checker data pegawai, payroll, pencairan
 * bank-wide Bekal Cuti/SPPD/Lembur).
 *
 * `approver_id`/`decided_at` (kolom lama) tetap berarti "keputusan
 * FINAL", diisi tahap 2 (atau tahap 1 bila ditolak di situ). Kolom
 * `atasan_approver_id`/`atasan_decided_at` HANYA jejak tahap 1.
 *
 * Persetujuan tahap 2 atas Cuti Tahunan (CT) MEMICU antrean Bekal Cuti
 * (lihat triggerBekalCutiIfFirstThisYear()) — SEKALI per pegawai per
 * tahun, amount_cents diisi manual saat pencairan (tidak ada rumus
 * resmi terverifikasi).
 */
final class LeaveApprovalQueueController extends Controller
{
    private const CRITICAL_DAYS = 3;

    private const WARNING_DAYS = 14;

    public function __construct(
        private readonly CurrentActor $actor,
        private readonly EmployeeRepository $employees,
        private readonly ReleaseLeaveBalance $releaseLeaveBalance,
    ) {}

    public function index(): View
    {
        $this->assertHasAnyRelevantRole();

        $today = new \DateTimeImmutable('today');

        $rows = DB::table('leave_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->leftJoin('wf_instance_steps as s', function ($join) {
                $join->on('s.instance_id', '=', 'r.wf_instance_id')->where('s.status', 'pending');
            })
            ->select(
                'r.id', 'r.request_number', 'r.leave_type', 'r.start_date', 'r.end_date',
                'r.total_days', 'r.reason', 'r.status', 's.sla_due_at',
                'e.id as employee_id', 'e.office_id', 'e.full_name', 'e.person_grade',
                'p.name as position_name',
                'o.name as office_name'
            )
            ->whereIn('r.status', ['pending', 'pending_pimpinan'])
            ->orderByRaw('s.sla_due_at IS NULL, s.sla_due_at')
            ->get()
            ->filter(function ($row) {
                $policy = $this->policyForTier($this->tierFor($row->status));

                return $policy !== null
                    && $policy->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId());
            })
            ->map(function ($row) use ($today) {
                if ($row->sla_due_at === null) {
                    $row->remaining_days = null;
                    $row->urgency = 'aman';
                } else {
                    $deadline = new \DateTimeImmutable($row->sla_due_at);
                    $remaining = (int) $today->diff($deadline)->format('%r%a');

                    $row->remaining_days = $remaining;
                    $row->urgency = match (true) {
                        $remaining <= self::CRITICAL_DAYS => 'kritis',
                        $remaining <= self::WARNING_DAYS => 'hati',
                        default => 'aman',
                    };
                }

                $row->tahap = $row->status === 'pending' ? 'Atasan Langsung' : 'Pimpinan Kantor';

                return $row;
            })
            ->values();

        $summary = [
            'menunggu' => $rows->count(),
            'kritis' => $rows->where('urgency', 'kritis')->count(),
            'hari' => $rows->sum('total_days'),
        ];

        return view('admin.leave-approval-queue', compact('rows', 'summary'));
    }

    /** Untuk badge notifikasi sidebar (ComputeNavigationBadgeCounts) — query+filter SAMA seperti index(), hanya count. */
    public function pendingCount(): int
    {
        if (! ($this->actor->hasRole(Role::AtasanLangsung->value) || $this->actor->hasRole(Role::PimpinanKantor->value) || $this->actor->hasRole(Role::Auditor->value))) {
            return 0;
        }

        return DB::table('leave_requests as r')
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
        return $this->decide($id, 'approve', 'Pengajuan cuti disetujui.');
    }

    public function reject(string $id): RedirectResponse
    {
        return $this->decide($id, 'reject', 'Pengajuan cuti ditolak.');
    }

    private function decide(string $id, string $decision, string $successMessage): RedirectResponse
    {
        $request = DB::table('leave_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select('r.id', 'r.status', 'r.leave_type', 'r.atasan_approver_id', 'e.id as employee_id', 'e.office_id')
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

        $affected = DB::table('leave_requests')
            ->where('id', $id)
            ->where('status', $request->status)
            ->update($updates);

        if ($affected > 0 && $decision === 'reject') {
            // Bug ditemukan lewat audit — SubmitLeaveRequest mendebit
            // used_days saat pengajuan, tapi tidak pernah ada yang
            // mengembalikannya kalau ditolak. Tanpa ini pegawai kehilangan
            // jatah cuti permanen untuk pengajuan yang tidak pernah disetujui.
            $this->releaseLeaveBalance->handle($id);
        }

        if ($affected > 0 && $tier === 'pimpinan' && $decision === 'approve' && $request->leave_type === 'CT') {
            $this->triggerBekalCutiIfFirstThisYear($id, $request->employee_id);
        }

        return redirect()
            ->route('admin.leave-approval-queue')
            ->with($affected > 0 ? 'sukses' : 'gagal', $affected > 0
                ? $successMessage
                : 'Pengajuan sudah diputus sebelumnya.');
    }

    /**
     * Bekal Cuti: SEKALI per pegawai per tahun, dipicu saat pegawai
     * SUDAH pernah mengonsumsi bucket current_year tahun ini (deduksi
     * terjadi saat SubmitLeaveRequest, bukan saat persetujuan — lihat
     * catatan kelas) DAN belum ada baris pencairan untuk tahun itu.
     * Tidak bisa dipastikan pengajuan INI PERSIS yang pertama (skema
     * tidak merekam pemecahan bucket per pengajuan) — proksi paling
     * masuk akal tanpa mengubah skema leave_balances/leave_requests.
     */
    private function triggerBekalCutiIfFirstThisYear(string $leaveRequestId, string $employeeId): void
    {
        $year = (int) date('Y');

        $alreadyTriggered = DB::table('pay_bekal_cuti_disbursements')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->exists();

        if ($alreadyTriggered) {
            return;
        }

        $hasConsumedCurrentYear = DB::table('leave_balances')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('bucket_type', 'current_year')
            ->where('used_days', '>', 0)
            ->exists();

        if (! $hasConsumedCurrentYear) {
            return;
        }

        DB::table('pay_bekal_cuti_disbursements')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'leave_request_id' => $leaveRequestId,
            'year' => $year,
            'amount_cents' => null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
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
