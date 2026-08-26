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
 * Online Assessment (BRD §5.4) — mulai + kirim jawaban. Scoring
 * OTOMATIS untuk soal `multiple_choice` (bandingkan jawaban vs
 * correct_option) LANGSUNG saat submit; soal `essay` menunggu
 * GradeAssessmentAttempt (penilaian manual — "multi-assessor" BRD
 * DISEDERHANAKAN jadi satu assessor per attempt, lihat docblock
 * GradeAssessmentAttempt). Kalau assessment TIDAK punya soal esai sama
 * sekali, attempt langsung `scored` (tidak perlu menunggu assessor).
 */
final class SubmitAssessmentAttempt
{
    private const POINTS_FOR_PASSING_ASSESSMENT = 15;

    public function __construct(
        private readonly AuditRepository $audit,
        private readonly AwardGamificationPoints $awardPoints,
    ) {}

    public function start(string $assessmentId, string $employeeId): string
    {
        $assessment = DB::table('lms_assessments')->where('id', $assessmentId)->where('is_active', true)->first();

        if ($assessment === null) {
            throw new InvalidArgumentException('Asesmen tidak ditemukan atau tidak aktif.');
        }

        $alreadyInProgress = DB::table('lms_assessment_attempts')
            ->where('assessment_id', $assessmentId)
            ->where('employee_id', $employeeId)
            ->where('status', 'in_progress')
            ->exists();

        if ($alreadyInProgress) {
            throw new InvalidArgumentException('Anda sudah punya pengerjaan asesmen ini yang belum dikirim.');
        }

        $id = (string) Uuid7::generate();

        DB::table('lms_assessment_attempts')->insert([
            'id' => $id,
            'assessment_id' => $assessmentId,
            'employee_id' => $employeeId,
            'status' => 'in_progress',
            'started_at' => new DateTimeImmutable,
            'created_at' => new DateTimeImmutable,
            'updated_at' => new DateTimeImmutable,
            'version' => 1,
        ]);

        return $id;
    }

    /** @param  array<string, string>  $answersByQuestionId */
    public function submit(string $attemptId, array $answersByQuestionId, AuditActor $actor): void
    {
        DB::transaction(function () use ($attemptId, $answersByQuestionId, $actor) {
            $attempt = DB::table('lms_assessment_attempts')->where('id', $attemptId)->lockForUpdate()->first();

            if ($attempt === null) {
                throw new RuntimeException('Pengerjaan asesmen tidak ditemukan.');
            }

            if ($attempt->status !== 'in_progress') {
                throw new InvalidArgumentException('Asesmen ini sudah dikirim sebelumnya.');
            }

            $questions = DB::table('lms_assessment_questions')
                ->where('assessment_id', $attempt->assessment_id)
                ->get();

            $now = new DateTimeImmutable;
            $hasEssay = false;

            foreach ($questions as $q) {
                $answerText = $answersByQuestionId[$q->id] ?? null;
                $scoreAwarded = null;

                if ($q->type === 'multiple_choice') {
                    $scoreAwarded = ($answerText !== null && $answerText === $q->correct_option) ? (float) $q->score_weight : 0.0;
                } else {
                    $hasEssay = true;
                }

                DB::table('lms_assessment_answers')->insert([
                    'id' => (string) Uuid7::generate(),
                    'attempt_id' => $attemptId,
                    'question_id' => $q->id,
                    'answer_text' => $answerText,
                    'score_awarded' => $scoreAwarded,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $assessment = DB::table('lms_assessments')->where('id', $attempt->assessment_id)->first();

            if ($assessment === null) {
                throw new RuntimeException('Asesmen untuk pengerjaan ini tidak ditemukan.');
            }

            $update = [
                'status' => $hasEssay ? 'submitted' : 'scored',
                'submitted_at' => $now,
                'updated_at' => $now,
                'version' => $attempt->version + 1,
            ];

            $passed = false;

            if (! $hasEssay) {
                $totalScore = (float) DB::table('lms_assessment_answers')->where('attempt_id', $attemptId)->sum('score_awarded');
                $passed = $totalScore >= (float) $assessment->passing_score;
                $update['total_score'] = $totalScore;
                $update['passed'] = $passed;
                $update['scored_at'] = $now;
            }

            DB::table('lms_assessment_attempts')->where('id', $attemptId)->update($update);

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
                action: AuditAction::Submitted,
            ));
        });
    }
}
