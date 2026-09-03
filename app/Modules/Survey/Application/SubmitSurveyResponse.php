<?php

declare(strict_types=1);

namespace App\Modules\Survey\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi satu survei — `svy_response_tokens` (UNIQUE survey_id+
 * employee_id) SELALU dicatat dengan employee_id (pegawai wajib
 * login untuk mengisi, tidak seperti Rekrutmen yang publik) untuk
 * mencegah pengisian ganda, TERLEPAS dari apakah survei itu sendiri
 * anonim — anonimitas hanya berarti `svy_responses.employee_id`
 * dikosongkan, bukan token pencegah duplikatnya.
 */
final class SubmitSurveyResponse
{
    /** @param  array<string, string>  $answers  question_id => nilai jawaban mentah */
    public function handle(string $surveyId, string $employeeId, array $answers): string
    {
        return DB::transaction(function () use ($surveyId, $employeeId, $answers) {
            $survey = DB::table('svy_surveys')->where('id', $surveyId)->lockForUpdate()->first();

            if ($survey === null) {
                throw new DomainException('Survei tidak ditemukan.');
            }

            if ($survey->status !== 'aktif') {
                throw new DomainException('Survei ini belum atau tidak lagi aktif.');
            }

            $today = (new DateTimeImmutable)->format('Y-m-d');

            if ($today < $survey->start_date || $today > $survey->end_date) {
                throw new DomainException('Survei ini berada di luar periode pengisian.');
            }

            $alreadyResponded = DB::table('svy_response_tokens')
                ->where('survey_id', $surveyId)
                ->where('employee_id', $employeeId)
                ->exists();

            if ($alreadyResponded) {
                throw new DomainException('Anda sudah pernah mengisi survei ini.');
            }

            $questions = DB::table('svy_questions')->where('survey_id', $surveyId)->get()->keyBy('id');

            foreach ($questions as $question) {
                /** @var object{id: string, question_text: string, question_type: string, options_json: ?string} $question */
                if (! array_key_exists($question->id, $answers) || trim($answers[$question->id]) === '') {
                    throw new DomainException("Pertanyaan \"{$question->question_text}\" wajib dijawab.");
                }

                $this->validateAnswer($question, $answers[$question->id]);
            }

            $now = new DateTimeImmutable;
            $responseId = (string) Uuid7::generate();

            DB::table('svy_responses')->insert([
                'id' => $responseId,
                'survey_id' => $surveyId,
                'employee_id' => $survey->is_anonymous ? null : $employeeId,
                'submitted_at' => $now,
            ]);

            foreach ($questions as $question) {
                DB::table('svy_answers')->insert([
                    'id' => (string) Uuid7::generate(),
                    'response_id' => $responseId,
                    'question_id' => $question->id,
                    'answer_value' => $answers[$question->id],
                ]);
            }

            DB::table('svy_response_tokens')->insert([
                'id' => (string) Uuid7::generate(),
                'survey_id' => $surveyId,
                'employee_id' => $employeeId,
                'created_at' => $now,
            ]);

            return $responseId;
        });
    }

    /** @param  object{id: string, question_text: string, question_type: string, options_json: ?string}  $question */
    private function validateAnswer(object $question, string $value): void
    {
        match ($question->question_type) {
            'nps_0_10' => $this->guardIntRange($question->question_text, $value, 0, 10),
            'rating_1_5' => $this->guardIntRange($question->question_text, $value, 1, 5),
            'pilihan_ganda' => $this->guardOption($question, $value),
            default => null, // teks — bebas, sudah dipastikan tidak kosong di atas
        };
    }

    private function guardIntRange(string $label, string $value, int $min, int $max): void
    {
        if (! ctype_digit($value) || (int) $value < $min || (int) $value > $max) {
            throw new DomainException("Jawaban untuk \"{$label}\" harus berupa angka {$min}-{$max}.");
        }
    }

    /** @param  object{question_text: string, options_json: ?string}  $question */
    private function guardOption(object $question, string $value): void
    {
        $options = json_decode($question->options_json ?? '[]', true);

        if (! is_array($options) || ! in_array($value, $options, true)) {
            throw new DomainException("Jawaban untuk \"{$question->question_text}\" tidak valid.");
        }
    }
}
