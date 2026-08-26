<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Evaluasi Pelatihan Level 1-4 (BRD §5.5). Level 1 diisi pegawai
 * sendiri, Level 3/4 diisi HC, Level 2 pakai ulang Assessment Center
 * (evaluation_type pre_test/post_test).
 */
final class LmsTrainingEvaluationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_mengisi_evaluasi_level_1_setelah_selesai(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->seedEnrollment($siti->employee_id, 'lulus');

        $response = $this->actingAs($siti)->post("/pelatihan/pendaftaran/{$enrollmentId}/evaluasi", [
            'satisfaction_score' => 5,
            'satisfaction_comments' => 'Sangat bermanfaat.',
        ]);

        $response->assertRedirect(route('lms.mine'));
        $evaluation = DB::table('lms_training_evaluations')->where('enrollment_id', $enrollmentId)->first();
        $this->assertSame(5, $evaluation->satisfaction_score);
    }

    public function test_evaluasi_level_1_ditolak_sebelum_pelatihan_selesai_dinilai(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->seedEnrollment($siti->employee_id, null);

        $response = $this->actingAs($siti)->get("/pelatihan/pendaftaran/{$enrollmentId}/evaluasi");

        $response->assertNotFound();
    }

    public function test_pegawai_lain_tidak_dapat_mengisi_evaluasi_bukan_miliknya(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->seedEnrollment($siti->employee_id, 'lulus');

        $ahmad = $this->userWithNrp('2015.07.0088');
        $response = $this->actingAs($ahmad)->get("/pelatihan/pendaftaran/{$enrollmentId}/evaluasi");

        $response->assertNotFound();
    }

    public function test_hc_dapat_mengisi_evaluasi_level_3_dan_4(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->seedEnrollment($siti->employee_id, 'lulus');

        $response = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/evaluasi/{$enrollmentId}", [
            'behavior_score' => 4,
            'behavior_comments' => 'Perubahan perilaku positif.',
            'impact_notes' => 'Lebih percaya diri melayani nasabah.',
        ]);

        $response->assertRedirect(route('lms.admin.evaluations.show', $enrollmentId));
        $evaluation = DB::table('lms_training_evaluations')->where('enrollment_id', $enrollmentId)->first();
        $this->assertSame(4, $evaluation->behavior_score);
        $this->assertSame('Lebih percaya diri melayani nasabah.', $evaluation->impact_notes);
    }

    public function test_laporan_pre_post_test_menghitung_selisih(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        $preAssessmentId = $this->seedAssessment('pre_test');
        $postAssessmentId = $this->seedAssessment('post_test');

        $this->seedScoredAttempt($preAssessmentId, $siti->employee_id, 60.0);
        $this->seedScoredAttempt($postAssessmentId, $siti->employee_id, 85.0);

        $response = $this->actingAs($this->hrAdmin())->get('/admin/pelatihan/evaluasi-pre-post');

        $response->assertOk();
        $response->assertSeeText('Siti Rahmawati');
    }

    public function test_peran_lain_ditolak_dari_evaluasi_hc(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->seedEnrollment($siti->employee_id, 'lulus');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get("/admin/pelatihan/evaluasi/{$enrollmentId}");

        $response->assertForbidden();
    }

    private function seedEnrollment(string $employeeId, ?string $completionStatus): string
    {
        $courseId = (string) Uuid7::generate();
        DB::table('lms_courses')->insert([
            'id' => $courseId, 'code' => 'CRS-'.uniqid(), 'title' => 'Kursus Uji',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $batchId = (string) Uuid7::generate();
        DB::table('lms_course_batches')->insert([
            'id' => $batchId, 'course_id' => $courseId, 'batch_code' => 'BATCH-'.uniqid(),
            'start_date' => now()->subDays(10)->format('Y-m-d'), 'end_date' => now()->subDays(8)->format('Y-m-d'),
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $enrollmentId = (string) Uuid7::generate();
        DB::table('lms_enrollments')->insert([
            'id' => $enrollmentId, 'enrollment_number' => 'PLT/TEST/'.uniqid(), 'batch_id' => $batchId,
            'employee_id' => $employeeId, 'status' => 'approved', 'completion_status' => $completionStatus,
            'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $enrollmentId;
    }

    private function seedAssessment(string $evaluationType): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_assessments')->insert([
            'id' => $id, 'title' => 'Asesmen '.$evaluationType, 'evaluation_type' => $evaluationType,
            'passing_score' => 70, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function seedScoredAttempt(string $assessmentId, string $employeeId, float $score): void
    {
        DB::table('lms_assessment_attempts')->insert([
            'id' => (string) Uuid7::generate(), 'assessment_id' => $assessmentId, 'employee_id' => $employeeId,
            'status' => 'scored', 'total_score' => $score, 'passed' => $score >= 70,
            'started_at' => now(), 'submitted_at' => now(), 'scored_at' => now(),
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);
    }

    private function hrAdmin(): User
    {
        return $this->userWithNrp('2021.05.0302');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
