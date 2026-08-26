<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use Illuminate\Support\Facades\DB;

/**
 * Rekomendasi pelatihan otomatis (BRD §5.1) — BERBASIS ATURAN, BUKAN
 * AI/ML (§5.13 AI-Based Recommendation tetap di luar scope, ditandai
 * OPSIONAL di BRD sendiri): gap kompetensi (required_level jabatan
 * MINUS current_level pegawai, default 0 kalau belum dinilai sama
 * sekali) dicocokkan ke kursus yang ditandai HC menutup kompetensi itu
 * (lms_course_competencies), diurutkan jumlah kompetensi bergap yang
 * ditutup — bukan skor kecocokan probabilistik.
 */
final class RecommendCoursesForGap
{
    /** @return array<int, object> setiap object punya properti id/title/gap_covered */
    public function forEmployee(string $employeeId): array
    {
        $positionId = DB::table('emp_employees')->where('id', $employeeId)->value('position_id');

        if ($positionId === null) {
            return [];
        }

        $required = DB::table('lms_position_competencies')
            ->where('position_id', $positionId)
            ->pluck('required_level', 'competency_id');

        if ($required->isEmpty()) {
            return [];
        }

        $current = DB::table('lms_employee_competencies')
            ->where('employee_id', $employeeId)
            ->pluck('current_level', 'competency_id');

        $gapCompetencyIds = [];

        foreach ($required as $competencyId => $requiredLevel) {
            $currentLevel = $current[$competencyId] ?? 0;

            if ($requiredLevel > $currentLevel) {
                $gapCompetencyIds[] = $competencyId;
            }
        }

        if ($gapCompetencyIds === []) {
            return [];
        }

        return DB::table('lms_course_competencies as cc')
            ->join('lms_courses as c', 'c.id', '=', 'cc.course_id')
            ->whereIn('cc.competency_id', $gapCompetencyIds)
            ->whereNull('c.deleted_at')
            ->where('c.is_active', true)
            ->select('c.id', 'c.title', DB::raw('count(*) as gap_covered'))
            ->groupBy('c.id', 'c.title')
            ->orderByDesc('gap_covered')
            ->get()
            ->all();
    }
}
