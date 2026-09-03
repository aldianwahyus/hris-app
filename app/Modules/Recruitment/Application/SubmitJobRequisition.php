<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan permintaan tambahan tenaga kerja (job requisition) —
 * maker-checker pola PERSIS SubmitNewEmployeeRequest: baris ini
 * hanya USULAN, belum membuka lowongan apa pun sampai hr_approver
 * memutuskan (lihat DecideJobRequisition).
 */
final class SubmitJobRequisition
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(
        string $officeId,
        string $positionId,
        int $requestedHeadcount,
        string $justification,
        string $requestedByEmployeeId,
        AuditActor $actor,
    ): string {
        return DB::transaction(function () use ($officeId, $positionId, $requestedHeadcount, $justification, $requestedByEmployeeId, $actor) {
            $now = new DateTimeImmutable;
            $id = (string) Uuid7::generate();

            DB::table('rec_job_requisitions')->insert([
                'id' => $id,
                'office_id' => $officeId,
                'position_id' => $positionId,
                'requested_headcount' => $requestedHeadcount,
                'justification' => $justification,
                'status' => 'pending',
                'requested_by' => $requestedByEmployeeId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'rec_job_requisition',
                auditableId: $id,
                action: AuditAction::Created,
                newValues: ['office_id' => $officeId, 'position_id' => $positionId, 'requested_headcount' => $requestedHeadcount],
            ));

            return $id;
        });
    }
}
