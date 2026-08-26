<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use Illuminate\Support\Facades\DB;

/**
 * Talent readiness score (BRD §5.6) — BENAR-BENAR DIHITUNG dari data
 * sistem (bukan input manual seperti performance_score/potential_score
 * di lms_talent_profiles): rata-rata tertimbang dari TIGA komponen,
 * masing-masing 0..1, HANYA komponen yang datanya tersedia yang dipakai
 * (bobot dinormalisasi ulang kalau ada yang kosong):
 *
 * - 40% capaian kompetensi jabatan (§5.1: rata-rata current_level/
 *   required_level seluruh kompetensi wajib jabatannya, dibatasi maks 1.0
 *   per kompetensi).
 * - 30% penyelesaian learning path (§5.2: proporsi kursus WAJIB yang
 *   sudah lulus).
 * - 30% potential_score manual (lms_talent_profiles, dinormalisasi /5).
 *
 * Null kalau TIDAK ADA satu pun dari 3 komponen yang tersedia (belum
 * ada data apa pun untuk dihitung).
 */
final class ComputeTalentReadiness
{
    public function __construct(private readonly ComputeLearningPathProgress $pathProgress) {}

    public function forEmployee(string $employeeId): ?float
    {
        $components = [];

        $competencyScore = $this->competencyAchievement($employeeId);
        if ($competencyScore !== null) {
            $components[] = ['score' => $competencyScore, 'weight' => 0.4];
        }

        $pathScore = $this->learningPathCompletion($employeeId);
        if ($pathScore !== null) {
            $components[] = ['score' => $pathScore, 'weight' => 0.3];
        }

        $potential = DB::table('lms_talent_profiles')->where('employee_id', $employeeId)->value('potential_score');
        if ($potential !== null) {
            $components[] = ['score' => min(1.0, ((int) $potential) / 5), 'weight' => 0.3];
        }

        if ($components === []) {
            return null;
        }

        $totalWeight = array_sum(array_column($components, 'weight'));
        $weightedSum = array_sum(array_map(fn ($c) => $c['score'] * $c['weight'], $components));

        return round($weightedSum / $totalWeight, 3);
    }

    private function competencyAchievement(string $employeeId): ?float
    {
        $positionId = DB::table('emp_employees')->where('id', $employeeId)->value('position_id');

        if ($positionId === null) {
            return null;
        }

        $required = DB::table('lms_position_competencies')->where('position_id', $positionId)->pluck('required_level', 'competency_id');

        if ($required->isEmpty()) {
            return null;
        }

        $current = DB::table('lms_employee_competencies')->where('employee_id', $employeeId)->pluck('current_level', 'competency_id');

        $ratios = [];
        foreach ($required as $competencyId => $requiredLevel) {
            $currentLevel = $current[$competencyId] ?? 0;
            $ratios[] = min(1.0, $requiredLevel > 0 ? $currentLevel / $requiredLevel : 1.0);
        }

        return array_sum($ratios) / count($ratios);
    }

    private function learningPathCompletion(string $employeeId): ?float
    {
        // (array) $p — hasil ComputeLearningPathProgress bertipe
        // `object` generik (bukan class bernama), akses lewat array
        // supaya PHPStan tidak menganggapnya properti tak dikenal.
        $progress = array_map(fn ($p) => (array) $p, $this->pathProgress->forEmployee($employeeId));
        $mandatory = array_filter($progress, fn ($p) => $p['is_mandatory']);

        if ($mandatory === []) {
            return null;
        }

        $lulus = array_filter($mandatory, fn ($p) => $p['status'] === 'lulus');

        return count($lulus) / count($mandatory);
    }
}
