<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * hr_approver (checker, `recruitment-requisition.decide` — permission
 * TERPISAH dari `recruitment.manage` operasional sehari-hari) memutuskan
 * job requisition. Pola PERSIS DecideNewEmployeeRequest/DecideSeparation.
 */
final class DecideJobRequisition
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function approve(string $requisitionId, AuditActor $actor, ?string $note = null): void
    {
        $this->decide($requisitionId, 'approved', AuditAction::Approved, $actor, $note);
    }

    public function reject(string $requisitionId, AuditActor $actor, ?string $note = null): void
    {
        $this->decide($requisitionId, 'rejected', AuditAction::Rejected, $actor, $note);
    }

    private function decide(string $requisitionId, string $status, AuditAction $action, AuditActor $actor, ?string $note): void
    {
        DB::transaction(function () use ($requisitionId, $status, $action, $actor, $note) {
            $requisition = DB::table('rec_job_requisitions')
                ->where('id', $requisitionId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($requisition === null) {
                throw new DomainException('Requisition tidak ditemukan atau sudah diputus sebelumnya.');
            }

            if ($requisition->requested_by === $actor->actorId) {
                throw new DomainException('Tidak dapat memutuskan requisition yang Anda ajukan sendiri.');
            }

            $now = new DateTimeImmutable;

            DB::table('rec_job_requisitions')->where('id', $requisitionId)->update([
                'status' => $status,
                'approver_id' => $actor->actorId,
                'decided_at' => $now,
                'decision_note' => $note,
                'updated_at' => $now,
                'version' => $requisition->version + 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'rec_job_requisition',
                auditableId: $requisitionId,
                action: $action,
                contextRef: $requisitionId,
            ));
        });
    }
}
