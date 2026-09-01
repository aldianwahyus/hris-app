<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Batal daftar sendiri — pola SAMA PERSIS
 * App\Modules\Leave\Application\CancelLeaveRequest. Kursi batch
 * dihitung dinamis dari status ('pending'/'approved', lihat
 * LmsEnrollmentController::index()) — begitu status berubah jadi
 * 'cancelled', kursi otomatis terbebas tanpa perlu menyentuh tabel lain.
 */
final class CancelEnrollment
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $enrollmentId, string $employeeId, AuditActor $actor): void
    {
        DB::transaction(function () use ($enrollmentId, $employeeId, $actor) {
            $enrollment = DB::table('lms_enrollments')
                ->where('id', $enrollmentId)
                ->lockForUpdate()
                ->first();

            if ($enrollment === null || $enrollment->employee_id !== $employeeId) {
                throw new DomainException('Pendaftaran pelatihan tidak ditemukan.');
            }

            if ($enrollment->status !== 'pending') {
                throw new DomainException('Pendaftaran yang sudah diproses tidak dapat dibatalkan sendiri — hubungi atasan Anda.');
            }

            DB::table('lms_enrollments')->where('id', $enrollmentId)->update([
                'status' => 'cancelled',
                'updated_at' => new DateTimeImmutable,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'lms_enrollment',
                auditableId: $enrollmentId,
                action: AuditAction::Cancelled,
                newValues: ['status' => 'cancelled'],
                contextRef: $enrollment->enrollment_number,
            ));
        });
    }
}
