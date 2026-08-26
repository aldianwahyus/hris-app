<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Employee\Domain\SkType;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat satu Surat Keputusan — tulis LANGSUNG tanpa persetujuan
 * (pola sama emp_sanctions dkk., lihat ResolveEmployeeForHrAction),
 * TAPI kalau $proposedChanges diisi (SK Mutasi/Promosi dengan target
 * kantor/jabatan) turut memicu pengajuan perubahan data induk lewat
 * SubmitEmployeeProfileChange yang SUDAH ADA — dipakai apa adanya,
 * tidak ditulis ulang. Transaksi bersarang (SAVEPOINT Postgres): kalau
 * validasi field pada SubmitEmployeeProfileChange gagal, seluruh SK
 * ikut gagal tersimpan — SK dan pengajuan perubahan data induknya
 * selalu konsisten, tidak pernah satu tersimpan tanpa yang lain.
 */
final class RecordDecisionLetter
{
    public function __construct(
        private readonly AuditRepository $audit,
        private readonly SubmitEmployeeProfileChange $submitProfileChange,
    ) {}

    /** @param  array<string, mixed>|null  $proposedChanges */
    public function handle(
        string $employeeId,
        SkType $skType,
        string $skNumber,
        DateTimeImmutable $skDate,
        ?DateTimeImmutable $effectiveDate,
        string $description,
        ?string $documentPath,
        ?string $documentOriginalName,
        ?array $proposedChanges,
        string $requestedBy,
        AuditActor $actor,
    ): string {
        return DB::transaction(function () use (
            $employeeId, $skType, $skNumber, $skDate, $effectiveDate, $description,
            $documentPath, $documentOriginalName, $proposedChanges, $requestedBy, $actor,
        ) {
            $profileChangeRequestId = null;

            if ($proposedChanges !== null) {
                $profileChangeRequestId = $this->submitProfileChange->handle(
                    $employeeId,
                    $proposedChanges,
                    $requestedBy,
                    $actor,
                );
            }

            $id = (string) Uuid7::generate();
            $now = new DateTimeImmutable;

            DB::table('emp_decision_letters')->insert([
                'id' => $id,
                'employee_id' => $employeeId,
                'sk_type' => $skType->value,
                'sk_number' => $skNumber,
                'sk_date' => $skDate->format('Y-m-d'),
                'effective_date' => $effectiveDate?->format('Y-m-d'),
                'description' => $description,
                'document_path' => $documentPath,
                'document_original_name' => $documentOriginalName,
                'profile_change_request_id' => $profileChangeRequestId,
                'created_by' => $actor->actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'employee_decision_letter',
                auditableId: $id,
                action: AuditAction::Created,
                newValues: [
                    'sk_type' => $skType->value,
                    'sk_number' => $skNumber,
                    'profile_change_request_id' => $profileChangeRequestId,
                ],
            ));

            return $id;
        });
    }
}
