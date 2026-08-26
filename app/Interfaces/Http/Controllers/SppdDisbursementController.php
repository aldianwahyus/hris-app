<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Sppd\Application\DisburseSppdRequest;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pencairan SPPD — tahap lanjutan setelah persetujuan, SEKARANG oleh
 * Admin Cabang (hr_admin, kantor sendiri) / Admin HC (hr_approver,
 * BANK_WIDE) menggantikan Treasury (peran itu berhenti dipakai di sini,
 * TIDAK dihapus dari enum — pola PERSIS OvertimeDisbursementController/
 * BekalCutiDisbursementController: dua rute terpisah, bukan satu aksi
 * bercabang). Larangan mencairkan pengajuan yang disetujui sendiri
 * ditegakkan di DisburseSppdRequest (§6.3).
 */
final class SppdDisbursementController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly DisburseSppdRequest $disburse,
    ) {}

    /** hr_admin — hanya SPPD kantornya sendiri. */
    public function indexForBranch(): View
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $rows = $this->baseQuery()->where('e.office_id', $officeId)->get();

        return view('admin.sppd-disbursement-queue', ['rows' => $rows, 'lingkup' => 'Kantor Anda']);
    }

    public function disburseForBranch(string $id, Request $request): RedirectResponse
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $requestOfficeId = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->where('r.id', $id)
            ->value('e.office_id');

        abort_if($requestOfficeId === null, 404);
        abort_unless($requestOfficeId === $officeId, 404);

        return $this->act($id, 'hr.sppd-disbursement.index', $request);
    }

    /** hr_approver/auditor — seluruh kantor. */
    public function indexForHc(): View
    {
        $rows = $this->baseQuery()->get();

        return view('admin.sppd-disbursement-queue', ['rows' => $rows, 'lingkup' => 'Seluruh Bank']);
    }

    /** Untuk badge notifikasi sidebar — HC bank-wide. */
    public function pendingCountHc(): int
    {
        return $this->baseQuery()->count();
    }

    /** Untuk badge notifikasi sidebar — Admin Cabang, kantornya sendiri. */
    public function pendingCountBranch(): int
    {
        $officeId = $this->actor->officeId();

        return $officeId === null ? 0 : $this->baseQuery()->where('e.office_id', $officeId)->count();
    }

    public function disburseForHc(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, 'admin.sppd-disbursement-queue', $request);
    }

    private function act(string $id, string $redirectRoute, Request $request): RedirectResponse
    {
        $validated = $request->validate(['disbursement_reference' => ['required', 'string', 'max:100']]);

        try {
            $this->disburse->handle($id, $validated['disbursement_reference'], $this->currentActor($request));
        } catch (DomainException $e) {
            return redirect()->route($redirectRoute)->with('gagal', $e->getMessage());
        }

        return redirect()->route($redirectRoute)->with('sukses', 'SPPD berhasil dicatat sebagai dicairkan.');
    }

    private function baseQuery(): Builder
    {
        return DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('emp_employees as approver', 'approver.id', '=', 'r.approver_id')
            ->select(
                'r.id', 'r.request_number', 'r.trip_category', 'r.destination',
                'r.start_date', 'r.end_date', 'r.total_days', 'r.currency',
                'r.uang_makan_cents', 'r.uang_saku_cents', 'r.estimasi_hotel_cents',
                'r.estimasi_angkutan_setempat_cents', 'r.estimasi_transportasi_tujuan_cents',
                'r.decided_at', 'e.full_name', 'approver.full_name as approver_name'
            )
            ->where('r.status', 'approved')
            ->orderBy('r.decided_at');
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
