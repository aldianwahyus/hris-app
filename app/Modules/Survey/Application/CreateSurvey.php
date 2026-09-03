<?php

declare(strict_types=1);

namespace App\Modules\Survey\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Membuat satu survei beserta pertanyaannya sekaligus (form builder
 * sederhana — HC susun seluruh pertanyaan lalu kirim SATU form).
 * Selalu dibuat berstatus 'draft'; diterbitkan (status='aktif')
 * terpisah lewat SurveyAdminController::publish() (transisi
 * sederhana, tidak butuh kelas Application sendiri).
 */
final class CreateSurvey
{
    public function __construct(private readonly AuditRepository $audit) {}

    /**
     * @param  array<int, array{question_text: string, question_type: string, options_json: ?string}>  $questions
     */
    public function handle(
        string $title,
        ?string $description,
        string $type,
        string $scope,
        ?string $officeId,
        bool $isAnonymous,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        array $questions,
        string $createdByEmployeeId,
        AuditActor $actor,
    ): string {
        if ($questions === []) {
            throw new DomainException('Survei wajib memiliki minimal satu pertanyaan.');
        }

        if ($startDate > $endDate) {
            throw new DomainException('Tanggal mulai tidak boleh setelah tanggal selesai.');
        }

        return DB::transaction(function () use ($title, $description, $type, $scope, $officeId, $isAnonymous, $startDate, $endDate, $questions, $createdByEmployeeId, $actor) {
            $now = new DateTimeImmutable;
            $surveyId = (string) Uuid7::generate();

            DB::table('svy_surveys')->insert([
                'id' => $surveyId,
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'scope' => $scope,
                'office_id' => $scope === 'office' ? $officeId : null,
                'is_anonymous' => $isAnonymous,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status' => 'draft',
                'created_by' => $createdByEmployeeId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            foreach ($questions as $order => $question) {
                DB::table('svy_questions')->insert([
                    'id' => (string) Uuid7::generate(),
                    'survey_id' => $surveyId,
                    'question_text' => $question['question_text'],
                    'question_type' => $question['question_type'],
                    'options_json' => $question['options_json'],
                    'display_order' => $order,
                ]);
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'svy_survey',
                auditableId: $surveyId,
                action: AuditAction::Created,
                newValues: ['title' => $title, 'type' => $type, 'scope' => $scope],
            ));

            return $surveyId;
        });
    }
}
