<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Evaluasi Pelatihan Level 1 — Kepuasan peserta (BRD §5.5, Kirkpatrick).
 * Diisi PEGAWAI sendiri untuk pendaftarannya SENDIRI, HANYA setelah
 * completion_status terisi (lulus/tidak lulus) — mengisi kepuasan atas
 * pelatihan yang belum selesai tidak masuk akal. Upsert (boleh diedit
 * ulang), satu baris per enrollment.
 */
final class SubmitTrainingEvaluation
{
    public function handle(string $enrollmentId, string $employeeId, ?int $satisfactionScore, ?string $satisfactionComments): void
    {
        $enrollment = DB::table('lms_enrollments')->where('id', $enrollmentId)->first();

        if ($enrollment === null) {
            throw new InvalidArgumentException('Pendaftaran pelatihan tidak ditemukan.');
        }

        if ($enrollment->employee_id !== $employeeId) {
            throw new InvalidArgumentException('Ini bukan pendaftaran pelatihan Anda.');
        }

        if ($enrollment->completion_status === null) {
            throw new InvalidArgumentException('Evaluasi hanya dapat diisi setelah pelatihan selesai dinilai.');
        }

        $now = new DateTimeImmutable;
        $existing = DB::table('lms_training_evaluations')->where('enrollment_id', $enrollmentId)->first();

        if ($existing === null) {
            DB::table('lms_training_evaluations')->insert([
                'id' => (string) Uuid7::generate(),
                'enrollment_id' => $enrollmentId,
                'satisfaction_score' => $satisfactionScore,
                'satisfaction_comments' => $satisfactionComments,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);
        } else {
            DB::table('lms_training_evaluations')->where('id', $existing->id)->update([
                'satisfaction_score' => $satisfactionScore,
                'satisfaction_comments' => $satisfactionComments,
                'updated_at' => $now,
                'version' => $existing->version + 1,
            ]);
        }
    }
}
