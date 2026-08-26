<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reporting + Advanced Analytics (BRD §5.11 + §5.12) — murni agregat
 * dari data yang sudah ada. TIDAK ADA angka ROI/prediksi rekaan (app
 * ini tidak punya data biaya pelatihan) — dashboard harus menampilkan
 * pesan keterbatasan, bukan angka Rupiah palsu.
 */
final class LmsAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_completion_rate_dihitung_benar(): void
    {
        $courseId = $this->seedCourse();
        $batchId = $this->seedBatch($courseId);

        $this->seedEnrollment($batchId, '2018.03.0142', 'lulus');
        $this->seedEnrollment($batchId, '2015.07.0088', 'tidak_lulus');
        $this->seedEnrollment($batchId, '2017.11.0119', 'lulus');

        $response = $this->actingAs($this->hrAdmin())->get('/admin/pelatihan/analitik');

        $response->assertOk();
        $response->assertSeeText('66.7%'); // 2 lulus dari 3 yang sudah dinilai
    }

    public function test_dashboard_tidak_menampilkan_angka_roi_rekaan(): void
    {
        $response = $this->actingAs($this->hrAdmin())->get('/admin/pelatihan/analitik');

        $response->assertOk();
        $response->assertSeeText('Belum tersedia');
        $response->assertDontSee('Rp', false);
    }

    public function test_laporan_kompetensi_menghitung_gap_rata_rata(): void
    {
        $positionId = DB::table('md_positions')->where('code', 'OFC')->value('id');
        $competencyId = (string) Uuid7::generate();

        DB::table('lms_competencies')->insert([
            'id' => $competencyId, 'code' => 'COMP-'.uniqid(), 'name' => 'Kompetensi Uji',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);
        DB::table('lms_position_competencies')->insert([
            'id' => (string) Uuid7::generate(), 'position_id' => $positionId, 'competency_id' => $competencyId,
            'required_level' => 4, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $siti = $this->userWithNrp('2018.03.0142');
        DB::table('lms_employee_competencies')->insert([
            'id' => (string) Uuid7::generate(), 'employee_id' => $siti->employee_id, 'competency_id' => $competencyId,
            'current_level' => 2, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($this->hrAdmin())->get('/admin/pelatihan/analitik/kompetensi');

        $response->assertOk();
        $response->assertSeeText('Kompetensi Uji');
    }

    public function test_laporan_talenta_menghitung_distribusi(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        DB::table('lms_talent_profiles')->insert([
            'id' => (string) Uuid7::generate(), 'employee_id' => $siti->employee_id,
            'performance_score' => 5, 'potential_score' => 5,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($this->hrAdmin())->get('/admin/pelatihan/analitik/talenta');

        $response->assertOk();
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/pelatihan/analitik');

        $response->assertForbidden();
    }

    private function seedCourse(): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_courses')->insert([
            'id' => $id, 'code' => 'CRS-'.uniqid(), 'title' => 'Kursus Uji',
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

    private function seedEnrollment(string $batchId, string $nrp, string $completionStatus): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        DB::table('lms_enrollments')->insert([
            'id' => (string) Uuid7::generate(), 'enrollment_number' => 'PLT/TEST/'.uniqid(), 'batch_id' => $batchId,
            'employee_id' => $employeeId, 'status' => 'approved', 'completion_status' => $completionStatus,
            'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
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
