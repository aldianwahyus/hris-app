<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\AccessPolicy;
use App\Modules\Access\Domain\OrganizationalScope;
use App\Modules\Access\Domain\Role;
use App\Modules\Employee\Contracts\EmployeeRepository;
use App\Notifications\RequestDecided;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        // Gerbang akses SESUNGGUHNYA sudah middleware `permission:izin-approval.view`
        // di routes/web.php (diatur lewat Peta Peran) — cek di sini cermin permission
        // itu, BUKAN daftar role hardcode terpisah (lihat catatan sama di
        // ApprovalQueueController::assertHasAnyRelevantRole()).
        abort_unless($this->actor->hasPermission('izin-approval.view'), 403, 'Anda tidak memiliki peran yang berwenang melihat antrean ini.');

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
        if (! $this->actor->hasPermission('izin-approval.view')) {
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
        return $this->decide($id, 'approved', 'Pengajuan izin disetujui.', null);
    }

    public function reject(string $id, Request $request): RedirectResponse
    {
        $note = $request->string('catatan')->toString();

        return $this->decide($id, 'rejected', 'Pengajuan izin ditolak.', $note !== '' ? $note : null);
    }

    public function downloadAttachment(string $id): StreamedResponse
    {
        $row = $this->baseQuery()->where('r.id', $id)->first();

        abort_if($row === null || $row->attachment_path === null, 404);

        abort_unless($this->policy()->canAccessRecord($row->office_id, $row->employee_id, $this->actor->employeeId()), 403);

        return Storage::disk('s3')->download($row->attachment_path, $row->attachment_original_name);
    }

    private function decide(string $id, string $status, string $successMessage, ?string $note): RedirectResponse
    {
        $request = DB::table('izin_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select('r.id', 'r.request_number', 'r.status', 'r.employee_id', 'e.office_id')
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
                'decision_note' => $note,
                'updated_at' => now(),
            ]);

        // Izin SATU tahap saja — setiap keputusan sudah final, jadi
        // notifikasi selalu dikirim (tidak ada tahap transisi seperti
        // Cuti/Lembur).
        if ($affected > 0) {
            $this->notifyRequester($request->employee_id, $request->request_number, $status === 'approved', $note);
        }

        return redirect()
            ->route('admin.izin-queue')
            ->with($affected > 0 ? 'sukses' : 'gagal', $affected > 0
                ? $successMessage
                : 'Pengajuan sudah diputus sebelumnya.');
    }

    /** Melewati pengiriman bila pegawai tidak (lagi) punya akun login — bukan kondisi yang perlu menggagalkan keputusan. */
    private function notifyRequester(string $employeeId, string $requestNumber, bool $approved, ?string $reason): void
    {
        $user = User::query()->where('employee_id', $employeeId)->first();

        $user?->notify(new RequestDecided($requestNumber, 'izin', $approved, $reason));
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
