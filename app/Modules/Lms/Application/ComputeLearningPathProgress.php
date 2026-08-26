<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use Illuminate\Support\Facades\DB;

/**
 * Realisasi Learning Path per pegawai — "IDP" (BRD §5.2 "Integrasi
 * dengan IDP") diinterpretasikan sebagai learning path jabatan pegawai
 * itu sendiri; realisasinya dibaca dari lms_enrollments yang SUDAH ADA
 * (bukan tabel IDP terpisah).
 */
final class ComputeLearningPathProgress
{
    /** @return array<int, object> setiap object punya properti course_id/course_title/sequence/is_mandatory/status */
    public function forEmployee(string $employeeId): array
    {
        $positionId = DB::table('emp_employees')->where('id', $employeeId)->value('position_id');

        if ($positionId === null) {
            return [];
        }

        $pathId = DB::table('lms_learning_paths')
            ->where('position_id', $positionId)
            ->where('is_active', true)
            ->value('id');

        if ($pathId === null) {
            return [];
        }

        $pathCourses = DB::table('lms_learning_path_courses as lpc')
            ->join('lms_courses as c', 'c.id', '=', 'lpc.course_id')
            ->where('lpc.learning_path_id', $pathId)
            ->select('lpc.course_id', 'c.title as course_title', 'lpc.sequence', 'lpc.is_mandatory')
            ->orderBy('lpc.sequence')
            ->get();

        if ($pathCourses->isEmpty()) {
            return [];
        }

        $enrollmentStatuses = DB::table('lms_enrollments as en')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->where('en.employee_id', $employeeId)
            ->whereIn('b.course_id', $pathCourses->pluck('course_id'))
            ->orderByDesc('en.requested_at')
            ->get(['b.course_id', 'en.status', 'en.completion_status']);

        return $pathCourses->map(function ($pc) use ($enrollmentStatuses) {
            $enrollment = $enrollmentStatuses->firstWhere('course_id', $pc->course_id);

            $pc->status = match (true) {
                $enrollment === null => 'belum_daftar',
                $enrollment->completion_status === 'lulus' => 'lulus',
                $enrollment->completion_status === 'tidak_lulus' => 'tidak_lulus',
                default => 'terdaftar',
            };

            return $pc;
        })->all();
    }
}
