<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Employee\Domain\EditableEmployeeField;
use App\Modules\Employee\Domain\ProfileChangeValidator;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * hr_admin (maker) mengajukan perubahan data induk pegawai — TIDAK
 * langsung menulis emp_employees, hanya mencatat usulan yang menunggu
 * hr_approver (checker). Mengikuti pola SPPD (bukan Cuti/Lembur):
 * satu checker tunggal tidak butuh Shared/Workflow, lihat migrasi
 * 2026_01_12_000001_create_employee_profile_change_requests.
 */
final class SubmitEmployeeProfileChange
{
    public function __construct(private readonly AuditRepository $audit) {}

    /** @param  array<string, mixed>  $proposedChanges */
    public function handle(
        string $employeeId,
        array $proposedChanges,
        string $requestedBy,
        AuditActor $actor,
    ): string {
        $current = DB::table('emp_employees')->where('id', $employeeId)->first();

        if ($current === null) {
            throw new RuntimeException("Pegawai (id: {$employeeId}) tidak ditemukan.");
        }

        $previousValues = self::snapshotRelevantFields($current, $proposedChanges);

        ProfileChangeValidator::validate($proposedChanges, $previousValues);

        return DB::transaction(function () use ($employeeId, $proposedChanges, $previousValues, $requestedBy, $actor) {
            $now = new DateTimeImmutable;
            $requestId = (string) Uuid7::generate();

            DB::table('emp_profile_change_requests')->insert([
                'id' => $requestId,
                'employee_id' => $employeeId,
                'requested_by' => $requestedBy,
                'proposed_changes' => json_encode($proposedChanges),
                'previous_values' => json_encode($previousValues),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'employee_profile_change',
                auditableId: $employeeId,
                action: AuditAction::Submitted,
                oldValues: $previousValues,
                newValues: $proposedChanges,
                contextRef: $requestId,
            ));

            return $requestId;
        });
    }

    /** @return array<int, string> */
    public static function editableFields(): array
    {
        return EditableEmployeeField::values();
    }

    /**
     * @param  array<string, mixed>  $proposedChanges
     * @return array<string, mixed>
     */
    private static function snapshotRelevantFields(object $current, array $proposedChanges): array
    {
        $snapshot = [];

        foreach (ProfileChangeValidator::fieldsToSnapshot($proposedChanges) as $field) {
            $snapshot[$field] = $current->{$field} ?? null;
        }

        return $snapshot;
    }
}
