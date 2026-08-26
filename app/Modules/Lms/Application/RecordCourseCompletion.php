<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * HC mencatat kelulusan/nilai atas pendaftaran yang SUDAH disetujui.
 * Kalau lulus: nomor sertifikat dibangkitkan DAN ditulis-turun
 * (write-through, SEARAH) ke emp_trainings/emp_certifications supaya
 * riwayatnya otomatis muncul di CV Saya tanpa pegawai mengetik ulang —
 * ini bagian konkret "terintegrasi langsung ke Data Kepegawaian".
 * emp_trainings/emp_certifications TETAP bisa diisi manual oleh pegawai
 * untuk riwayat di luar LMS, perilaku itu tidak diubah.
 */
final class RecordCourseCompletion
{
    private const POINTS_FOR_PASSING_COURSE = 10;

    public function __construct(
        private readonly AuditRepository $audit,
        private readonly AwardGamificationPoints $awardPoints,
    ) {}

    public function handle(string $enrollmentId, string $completionStatus, ?string $score, AuditActor $actor, string $recordedBy): void
    {
        if (! in_array($completionStatus, ['lulus', 'tidak_lulus'], true)) {
            throw new InvalidArgumentException('Status kelulusan tidak dikenali.');
        }

        DB::transaction(function () use ($enrollmentId, $completionStatus, $score, $actor, $recordedBy) {
            $enrollment = DB::table('lms_enrollments as en')
                ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
                ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
                ->where('en.id', $enrollmentId)
                ->select('en.*', 'b.course_id', 'b.start_date', 'b.end_date', 'c.code as course_code', 'c.title as course_title')
                ->lockForUpdate()
                ->first();

            if ($enrollment === null) {
                throw new RuntimeException('Pendaftaran pelatihan tidak ditemukan.');
            }

            if ($enrollment->status !== 'approved') {
                throw new InvalidArgumentException('Kelulusan hanya dapat dicatat untuk pendaftaran yang sudah disetujui.');
            }

            $now = new DateTimeImmutable;
            $certificateNumber = null;

            if ($completionStatus === 'lulus') {
                $certificateNumber = $this->nextCertificateNumber($enrollment->course_code, $now);
            }

            DB::table('lms_enrollments')->where('id', $enrollmentId)->update([
                'completion_status' => $completionStatus,
                'score' => $score,
                'completed_at' => $now,
                'certificate_number' => $certificateNumber,
                'recorded_by' => $recordedBy,
                'updated_at' => $now,
            ]);

            if ($completionStatus === 'lulus') {
                DB::table('emp_trainings')->insert([
                    'id' => (string) Uuid7::generate(),
                    'employee_id' => $enrollment->employee_id,
                    'training_name' => $enrollment->course_title,
                    'organizer' => 'Internal — LMS',
                    'start_date' => $enrollment->start_date,
                    'end_date' => $enrollment->end_date,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);

                DB::table('emp_certifications')->insert([
                    'id' => (string) Uuid7::generate(),
                    'employee_id' => $enrollment->employee_id,
                    'certification_name' => $enrollment->course_title,
                    'issuer' => 'Internal — LMS',
                    'issued_date' => $now->format('Y-m-d'),
                    'certificate_number' => $certificateNumber,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);

                $this->awardPoints->handle(
                    employeeId: $enrollment->employee_id,
                    points: self::POINTS_FOR_PASSING_COURSE,
                    reason: "Lulus pelatihan: {$enrollment->course_title}",
                    sourceType: 'lms_enrollment',
                    sourceId: $enrollmentId,
                );
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'lms_enrollment',
                auditableId: $enrollmentId,
                action: AuditAction::Updated,
                newValues: ['completion_status' => $completionStatus, 'score' => $score, 'certificate_number' => $certificateNumber],
            ));
        });
    }

    private function nextCertificateNumber(string $courseCode, DateTimeImmutable $now): string
    {
        $prefix = sprintf('SERT/%s/%s/', $courseCode, $now->format('Y'));

        $count = DB::table('lms_enrollments')
            ->where('certificate_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
