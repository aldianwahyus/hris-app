<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Learning Path & Career Development (BRD §5.2) — jalur per jabatan +
 * "IDP" (progres realisasi dari lms_enrollments yang sudah ada, lihat
 * ComputeLearningPathProgress, TIDAK ada tabel IDP terpisah).
 */
final class LmsLearningPathTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hc_dapat_membuat_learning_path_untuk_jabatan(): void
    {
        $positionId = DB::table('md_positions')->where('code', 'OFC')->value('id');

        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/learning-path', [
            'position_id' => $positionId,
            'title' => 'Jalur Uji Officer',
        ]);

        $response->assertRedirect(route('lms.admin.learning-paths.index'));
        $this->assertSame(1, DB::table('lms_learning_paths')->where('position_id', $positionId)->count());
    }

    public function test_hc_dapat_menambah_kursus_ke_learning_path(): void
    {
        $pathId = $this->seedPath();
        $courseId = $this->seedCourse('Kursus A');

        $response = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/learning-path/{$pathId}/kursus", [
            'course_id' => $courseId,
            'sequence' => 1,
            'is_mandatory' => '1',
        ]);

        $response->assertRedirect(route('lms.admin.learning-paths.show', $pathId));
        $this->assertSame(1, DB::table('lms_learning_path_courses')->where('learning_path_id', $pathId)->count());
    }

    public function test_progres_learning_path_pegawai_terhitung_benar(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $positionId = DB::table('emp_employees')->where('id', $siti->employee_id)->value('position_id');

        $pathId = (string) Uuid7::generate();
        DB::table('lms_learning_paths')->insert([
            'id' => $pathId, 'position_id' => $positionId, 'title' => 'Jalur Siti',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $courseLulusId = $this->seedCourse('Kursus Lulus');
        $courseBelumId = $this->seedCourse('Kursus Belum');

        DB::table('lms_learning_path_courses')->insert([
            ['id' => (string) Uuid7::generate(), 'learning_path_id' => $pathId, 'course_id' => $courseLulusId, 'sequence' => 1, 'is_mandatory' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1],
            ['id' => (string) Uuid7::generate(), 'learning_path_id' => $pathId, 'course_id' => $courseBelumId, 'sequence' => 2, 'is_mandatory' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1],
        ]);

        // Siti sudah lulus kursus pertama (via batch+enrollment).
        $batchId = $this->seedBatch($courseLulusId);
        DB::table('lms_enrollments')->insert([
            'id' => (string) Uuid7::generate(), 'enrollment_number' => 'PLT/TEST/'.uniqid(), 'batch_id' => $batchId,
            'employee_id' => $siti->employee_id, 'status' => 'approved', 'completion_status' => 'lulus',
            'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($siti)->get('/pelatihan/rencana-pengembangan');

        $response->assertOk();
        $response->assertSeeText('Kursus Lulus');
        $response->assertSeeText('Lulus');
        $response->assertSeeText('Kursus Belum');
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/pelatihan/learning-path');

        $response->assertForbidden();
    }

    private function seedPath(): string
    {
        $positionId = DB::table('md_positions')->where('code', 'OFC')->value('id');
        $id = (string) Uuid7::generate();

        DB::table('lms_learning_paths')->insert([
            'id' => $id, 'position_id' => $positionId, 'title' => 'Jalur Uji',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function seedCourse(string $title): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_courses')->insert([
            'id' => $id, 'code' => 'CRS-'.uniqid(), 'title' => $title,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function seedBatch(string $courseId): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_course_batches')->insert([
            'id' => $id, 'course_id' => $courseId, 'batch_code' => 'BATCH-'.uniqid(),
            'start_date' => now()->subDays(10)->format('Y-m-d'), 'end_date' => now()->subDays(8)->format('Y-m-d'),
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
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
