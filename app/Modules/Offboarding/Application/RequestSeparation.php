<?php

declare(strict_types=1);

namespace App\Modules\Offboarding\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan pemisahan (offboarding) satu pegawai — maker-checker
 * pola PERSIS SubmitNewEmployeeRequest: baris ini hanya USULAN,
 * belum mengubah data pegawai apa pun sampai hr_approver memutuskan
 * (lihat DecideSeparation).
 */
final class RequestSeparation
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(
        string $employeeId,
        string $separationType,
        string $reason,
        DateTimeImmutable $requestedLastDate,
        string $requestedByEmployeeId,
        AuditActor $actor,
    ): string {
        $alreadyPending = DB::table('off_separation_requests')
            ->where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            throw new DomainException('Pegawai ini sudah memiliki pengajuan pemisahan yang masih menunggu keputusan.');
        }

        if (DB::table('emp_employees')->where('id', $employeeId)->value('separated_at') !== null) {
            throw new DomainException('Pegawai ini sudah tidak aktif.');
        }

        return DB::transaction(function () use ($employeeId, $separationType, $reason, $requestedLastDate, $requestedByEmployeeId, $actor) {
            $now = new DateTimeImmutable;
            $id = (string) Uuid7::generate();

            DB::table('off_separation_requests')->insert([
                'id' => $id,
                'employee_id' => $employeeId,
                'separation_type' => $separationType,
                'reason' => $reason,
                'requested_last_date' => $requestedLastDate->format('Y-m-d'),
                'status' => 'pending',
                'requested_by' => $requestedByEmployeeId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'off_separation_request',
                auditableId: $id,
                action: AuditAction::Created,
                newValues: ['employee_id' => $employeeId, 'separation_type' => $separationType],
            ));

            return $id;
        });
    }
}
