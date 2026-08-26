<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pendaftaran pegawai ke satu batch pelatihan dari layar ESS — langsung
 * ke antrean Atasan Langsung (1 tahap, lihat LmsEnrollmentApprovalController).
 * Workflow Engine di sini HANYA untuk pelacakan SLA/pengingat, bukan
 * penentu approver (pola sama SubmitShiftSwapRequest/SubmitLeaveRequest).
 */
final class EnrollInCourseBatch
{
    public function __construct(
        private readonly WorkflowInstanceRepository $workflow,
        private readonly AuditRepository $audit,
    ) {}

    public function handle(string $employeeId, string $batchId, AuditActor $actor): string
    {
        return DB::transaction(function () use ($employeeId, $batchId, $actor) {
            $batch = DB::table('lms_course_batches')->where('id', $batchId)->lockForUpdate()->first();

            if ($batch === null) {
                throw new InvalidArgumentException('Jadwal pelatihan tidak ditemukan.');
            }

            if ($batch->status !== 'scheduled') {
                throw new InvalidArgumentException('Jadwal pelatihan ini sudah tidak menerima pendaftaran.');
            }

            $today = (new DateTimeImmutable('today'))->format('Y-m-d');

            if ($batch->registration_deadline !== null && $batch->registration_deadline < $today) {
                throw new InvalidArgumentException('Batas waktu pendaftaran untuk jadwal ini sudah lewat.');
            }

            $alreadyEnrolled = DB::table('lms_enrollments')
                ->where('batch_id', $batchId)
                ->where('employee_id', $employeeId)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($alreadyEnrolled) {
                throw new InvalidArgumentException('Anda sudah terdaftar pada jadwal pelatihan ini.');
            }

            if ($batch->capacity !== null) {
                $takenSeats = DB::table('lms_enrollments')
                    ->where('batch_id', $batchId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                if ($takenSeats >= $batch->capacity) {
                    throw new InvalidArgumentException('Kuota jadwal pelatihan ini sudah penuh.');
                }
            }

            $now = new DateTimeImmutable;
            $enrollmentId = (string) Uuid7::generate();

            DB::table('lms_enrollments')->insert([
                'id' => $enrollmentId,
                'enrollment_number' => $this->nextEnrollmentNumber($now),
                'batch_id' => $batchId,
                'employee_id' => $employeeId,
                'status' => 'pending',
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $instance = $this->workflow->startFor('lms_enrollment', $enrollmentId, $employeeId, $now);

            DB::table('lms_enrollments')->where('id', $enrollmentId)->update(['wf_instance_id' => $instance->id]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'lms_enrollment',
                auditableId: $enrollmentId,
                action: AuditAction::Submitted,
                newValues: ['batch_id' => $batchId],
            ));

            return DB::table('lms_enrollments')->where('id', $enrollmentId)->value('enrollment_number');
        });
    }

    private function nextEnrollmentNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('PLT/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('lms_enrollments')
            ->where('enrollment_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
