<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Penilaian manual soal esai (BRD §5.4 "multi-assessor" —
 * DISEDERHANAKAN jadi SATU assessor per attempt, bukan konsensus
 * banyak penilai; assessor bisa DITUGASKAN berbeda per assessment,
 * cukup untuk kebutuhan umum tanpa kerumitan alur konsensus). Setelah
 * SEMUA soal esai attempt itu ternilai, total skor dihitung otomatis
 * (digabung dengan skor multiple_choice yang sudah otomatis sejak
 * submit) dan status jadi `scored`.
 */
final class GradeAssessmentAttempt
{
    private const POINTS_FOR_PASSING_ASSESSMENT = 15;

    public function __construct(
        private readonly AuditRepository $audit,
        private readonly AwardGamificationPoints $awardPoints,
    ) {}

    /** @param  array<string, string>  $scoresByQuestionId  question_id => skor (string numerik) */
    public function handle(string $attemptId, array $scoresByQuestionId, string $assessorId, AuditActor $actor): void
    {
        DB::transaction(function () use ($attemptId, $scoresByQuestionId, $assessorId, $actor) {
            $attempt = DB::table('lms_assessment_attempts')->where('id', $attemptId)->lockForUpdate()->first();

            if ($attempt === null) {
                throw new RuntimeException('Pengerjaan asesmen tidak ditemukan.');
            }

            if ($attempt->status !== 'submitted') {
                throw new InvalidArgumentException('Asesmen ini tidak sedang menunggu penilaian.');
            }

            $now = new DateTimeImmutable;

            foreach ($scoresByQuestionId as $questionId => $score) {
                DB::table('lms_assessment_answers')
                    ->where('attempt_id', $attemptId)
                    ->where('question_id', $questionId)
                    ->update(['score_awarded' => (float) $score, 'updated_at' => $now]);
            }

            $stillUngraded = DB::table('lms_assessment_answers')->where('attempt_id', $attemptId)->whereNull('score_awarded')->exists();

            if ($stillUngraded) {
                DB::table('lms_assessment_attempts')->where('id', $attemptId)->update([
                    'assessor_id' => $assessorId,
                    'updated_at' => $now,
                    'version' => $attempt->version + 1,
                ]);

                return;
            }

            $assessment = DB::table('lms_assessments')->where('id', $attempt->assessment_id)->first();

            if ($assessment === null) {
                throw new RuntimeException('Asesmen untuk pengerjaan ini tidak ditemukan.');
            }

            $totalScore = (float) DB::table('lms_assessment_answers')->where('attempt_id', $attemptId)->sum('score_awarded');
            $passed = $totalScore >= (float) $assessment->passing_score;

            DB::table('lms_assessment_attempts')->where('id', $attemptId)->update([
                'status' => 'scored',
                'total_score' => $totalScore,
                'passed' => $passed,
                'assessor_id' => $assessorId,
                'scored_at' => $now,
                'updated_at' => $now,
                'version' => $attempt->version + 1,
            ]);

            if ($passed) {
                $this->awardPoints->handle(
                    employeeId: $attempt->employee_id,
                    points: self::POINTS_FOR_PASSING_ASSESSMENT,
                    reason: "Lulus asesmen: {$assessment->title}",
                    sourceType: 'lms_assessment_attempt',
                    sourceId: $attemptId,
                );
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'lms_assessment_attempt',
                auditableId: $attemptId,
                action: AuditAction::Updated,
                newValues: ['total_score' => $totalScore],
            ));
        });
    }
}
