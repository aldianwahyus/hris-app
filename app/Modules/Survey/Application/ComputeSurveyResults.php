<?php

declare(strict_types=1);

namespace App\Modules\Survey\Application;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung hasil satu survei per pertanyaan — skor eNPS (%promoter
 * − %detractor) untuk nps_0_10, rata-rata+distribusi untuk
 * rating_1_5, hitungan per opsi untuk pilihan_ganda, daftar jawaban
 * apa adanya untuk teks (jawaban bebas TIDAK diringkas).
 */
final class ComputeSurveyResults
{
    /** @return array{response_count: int, questions: array<int, array<string, mixed>>} */
    public function handle(string $surveyId): array
    {
        $questions = DB::table('svy_questions')->where('survey_id', $surveyId)->orderBy('display_order')->get();
        $responseCount = DB::table('svy_responses')->where('survey_id', $surveyId)->count();

        $results = $questions->map(function ($question) {
            $values = DB::table('svy_answers')
                ->where('question_id', $question->id)
                ->pluck('answer_value');

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'answer_count' => $values->count(),
                'summary' => match ($question->question_type) {
                    'nps_0_10' => $this->npsSummary($values),
                    'rating_1_5' => $this->ratingSummary($values),
                    'pilihan_ganda' => $this->choiceSummary($values, $question->options_json),
                    default => ['jawaban' => $values->all()],
                },
            ];
        })->all();

        return ['response_count' => $responseCount, 'questions' => $results];
    }

    /**
     * @param  Collection<int, string>  $values
     * @return array{score: float, promoter: int, passive: int, detractor: int, total: int}
     */
    private function npsSummary(Collection $values): array
    {
        $ints = $values->map(fn ($v) => (int) $v);
        $total = $ints->count();

        if ($total === 0) {
            return ['score' => 0.0, 'promoter' => 0, 'passive' => 0, 'detractor' => 0, 'total' => 0];
        }

        $promoter = $ints->filter(fn ($v) => $v >= 9)->count();
        $detractor = $ints->filter(fn ($v) => $v <= 6)->count();
        $passive = $total - $promoter - $detractor;

        $score = round((($promoter / $total) - ($detractor / $total)) * 100, 1);

        return ['score' => $score, 'promoter' => $promoter, 'passive' => $passive, 'detractor' => $detractor, 'total' => $total];
    }

    /**
     * @param  Collection<int, string>  $values
     * @return array{average: float, distribution: array<int, int>, total: int}
     */
    private function ratingSummary(Collection $values): array
    {
        $ints = $values->map(fn ($v) => (int) $v);
        $total = $ints->count();
        $distribution = [];

        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = $ints->filter(fn ($v) => $v === $i)->count();
        }

        $average = $total > 0 ? round($ints->sum() / $total, 2) : 0.0;

        return ['average' => $average, 'distribution' => $distribution, 'total' => $total];
    }

    /**
     * @param  Collection<int, string>  $values
     * @return array{counts: array<string, int>, total: int}
     */
    private function choiceSummary(Collection $values, ?string $optionsJson): array
    {
        $options = json_decode($optionsJson ?? '[]', true);
        $options = is_array($options) ? $options : [];
        $counts = array_fill_keys($options, 0);

        foreach ($values as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return ['counts' => $counts, 'total' => $values->count()];
    }
}
