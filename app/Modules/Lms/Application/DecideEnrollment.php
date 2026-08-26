<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Keputusan Atasan Langsung atas pendaftaran pelatihan — pola PERSIS
 * DecideOutsideAttendanceRequest (lock-for-update, guard status pending,
 * audit Approved/Rejected dengan contextRef nomor pendaftaran).
 */
final class DecideEnrollment
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function approve(string $enrollmentId, string $approverId, AuditActor $actor): void
    {
        $this->decide($enrollmentId, $approverId, 'approved', AuditAction::Approved, $actor);
    }

    public function reject(string $enrollmentId, string $approverId, AuditActor $actor): void
    {
        $this->decide($enrollmentId, $approverId, 'rejected', AuditAction::Rejected, $actor);
    }

    private function decide(string $enrollmentId, string $approverId, string $status, AuditAction $action, AuditActor $actor): void
    {
        DB::transaction(function () use ($enrollmentId, $approverId, $status, $action, $actor) {
            $enrollment = DB::table('lms_enrollments')->where('id', $enrollmentId)->lockForUpdate()->first();

            if ($enrollment === null) {
                throw new RuntimeException('Pendaftaran pelatihan tidak ditemukan.');
            }

            if ($enrollment->status !== 'pending') {
                return;
            }

            $now = new DateTimeImmutable;

            DB::table('lms_enrollments')->where('id', $enrollmentId)->update([
                'status' => $status,
                'approver_id' => $approverId,
                'decided_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'lms_enrollment',
                auditableId: $enrollmentId,
                action: $action,
                contextRef: $enrollment->enrollment_number,
            ));
        });
    }
}
