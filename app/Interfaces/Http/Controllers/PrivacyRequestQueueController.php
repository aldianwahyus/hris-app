<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Privacy\Application\ReviewDeletionRequest;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Peninjauan permintaan penghapusan data (UU PDP, Fase 2) —
 * `permission:privacy-request.manage` (hr_approver, lihat migrasi
 * permission) — SENGAJA bank-wide tanpa lingkup kantor (data pribadi
 * bukan urusan operasional per-kantor, pola SAMA Whistleblowing).
 */
final class PrivacyRequestQueueController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ReviewDeletionRequest $review,
    ) {}

    public function pendingCount(): int
    {
        return DB::table('pdp_deletion_requests')->where('status', 'pending')->count();
    }

    public function index(Request $request): View
    {
        $statusFilter = $request->string('status')->toString();

        $requests = DB::table('pdp_deletion_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->when($statusFilter !== '', fn ($q) => $q->where('r.status', $statusFilter))
            ->when($statusFilter === '', fn ($q) => $q->where('r.status', 'pending'))
            ->select('r.id', 'r.reason', 'r.status', 'r.notes', 'r.created_at', 'e.full_name', 'e.nrp')
            ->orderByDesc('r.created_at')
            ->get();

        return view('admin.privacy-request-queue', ['requests' => $requests, 'statusFilter' => $statusFilter]);
    }

    public function review(Request $request, string $id): RedirectResponse
    {
        return $this->decide($request, fn (string $id, AuditActor $actor, ?string $notes) => $this->review->review($id, $actor, $notes), $id, 'Permintaan ditandai akan ditindaklanjuti.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        return $this->decide($request, fn (string $id, AuditActor $actor, ?string $notes) => $this->review->reject($id, $actor, $notes), $id, 'Permintaan ditolak.');
    }

    public function complete(Request $request, string $id): RedirectResponse
    {
        return $this->decide($request, fn (string $id, AuditActor $actor, ?string $notes) => $this->review->complete($id, $actor, $notes), $id, 'Permintaan ditandai selesai ditangani.');
    }

    private function decide(Request $request, callable $action, string $id, string $successMessage): RedirectResponse
    {
        $notes = $request->string('catatan')->toString();
        $actor = new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        try {
            $action($id, $actor, $notes !== '' ? $notes : null);
        } catch (DomainException $e) {
            return redirect()->route('admin.privacy-request-queue')->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.privacy-request-queue')->with('sukses', $successMessage);
    }
}
