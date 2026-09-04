<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * hr_approver meninjau permintaan penghapusan data (UU PDP, Fase 2).
 * `reviewed`/`rejected` HANYA dari 'pending'; `completed` HANYA dari
 * 'reviewed' — 'completed' menandai penanganan data SUNGGUHAN
 * (penghapusan/anonimisasi manual di luar sistem ini) sudah
 * dituntaskan, dicatat TERPISAH dari keputusan tinjau supaya jejak
 * "kapan diputuskan" vs "kapan benar-benar selesai dikerjakan" tidak
 * bercampur.
 */
final class ReviewDeletionRequest
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function review(string $requestId, AuditActor $actor, ?string $notes = null): void
    {
        $this->transition($requestId, 'pending', 'reviewed', AuditAction::Approved, $actor, $notes);
    }

    public function reject(string $requestId, AuditActor $actor, ?string $notes = null): void
    {
        $this->transition($requestId, 'pending', 'rejected', AuditAction::Rejected, $actor, $notes);
    }

    public function complete(string $requestId, AuditActor $actor, ?string $notes = null): void
    {
        $this->transition($requestId, 'reviewed', 'completed', AuditAction::Executed, $actor, $notes);
    }

    private function transition(string $requestId, string $fromStatus, string $toStatus, AuditAction $action, AuditActor $actor, ?string $notes): void
    {
        DB::transaction(function () use ($requestId, $fromStatus, $toStatus, $action, $actor, $notes) {
            $request = DB::table('pdp_deletion_requests')->where('id', $requestId)->lockForUpdate()->first();

            if ($request === null) {
                throw new DomainException('Permintaan tidak ditemukan.');
            }

            if ($request->status !== $fromStatus) {
                throw new DomainException("Permintaan ini berstatus \"{$request->status}\", bukan \"{$fromStatus}\".");
            }

            $now = new DateTimeImmutable;

            DB::table('pdp_deletion_requests')->where('id', $requestId)->update([
                'status' => $toStatus,
                'reviewed_by' => $actor->actorId,
                'reviewed_at' => $now,
                'notes' => $notes,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'pdp_deletion_request',
                auditableId: $requestId,
                action: $action,
                contextRef: $requestId,
            ));
        });
    }
}
