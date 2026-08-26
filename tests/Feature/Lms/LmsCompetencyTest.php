<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Competency-Based Learning (BRD §5.1) — peta kompetensi jabatan, skill
 * mapping individu, gap, dan rekomendasi kursus berbasis aturan
 * (RecommendCoursesForGap) — bukan AI/ML.
 */
final class LmsCompetencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hc_dapat_menambah_kompetensi(): void
    {
        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/kompetensi', [
            'code' => 'COMP-UJI',
            'name' => 'Kompetensi Uji',
            'category' => 'Teknis',
        ]);

        $response->assertRedirect(route('lms.admin.competencies.index'));
        $this->assertSame(1, DB::table('lms_competencies')->where('code', 'COMP-UJI')->count());
    }

    public function test_hc_dapat_memetakan_kompetensi_ke_jabatan(): void
    {
        $competencyId = $this->seedCompetency();
        $positionId = DB::table('md_positions')->where('code', 'OFC')->value('id');

        $response = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/kompetensi/jabatan/{$positionId}", [
            'required_level' => [$competencyId => 4],
        ]);

        $response->assertRedirect(route('lms.admin.competencies.map-position', $positionId));
        $this->assertSame(4, DB::table('lms_position_competencies')->where('position_id', $positionId)->where('competency_id', $competencyId)->value('required_level'));
    }

    public function test_gap_kompetensi_menghasilkan_rekomendasi_kursus_yang_menutupnya(): void
    {
        $competencyId = $this->seedCompetency();
        $siti = $this->userWithNrp('2018.03.0142');
        $positionId = DB::table('emp_employees')->where('id', $siti->employee_id)->value('position_id');

        // Jabatan Siti butuh level 4, dia belum dinilai sama sekali (gap = 4).
        DB::table('lms_position_competencies')->insert([
            'id' => (string) Uuid7::generate(), 'position_id' => $positionId, 'competency_id' => $competencyId,
            'required_level' => 4, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $courseId = $this->seedCourse();
        DB::table('lms_course_competencies')->insert([
            'id' => (string) Uuid7::generate(), 'course_id' => $courseId, 'competency_id' => $competencyId, 'created_at' => now(),
        ]);

        $response = $this->actingAs($this->hrAdmin())->get("/admin/pelatihan/kompetensi-pegawai/{$siti->employee_id}");

        $response->assertOk();
        $response->assertSeeText('Gap 4');
        $response->assertSeeText('Kursus Uji');
    }

    public function test_menilai_level_kompetensi_menutup_gap(): void
    {
        $competencyId = $this->seedCompetency();
        $siti = $this->userWithNrp('2018.03.0142');
        $positionId = DB::table('emp_employees')->where('id', $siti->employee_id)->value('position_id');

        DB::table('lms_position_competencies')->insert([
            'id' => (string) Uuid7::generate(), 'position_id' => $positionId, 'competency_id' => $competencyId,
            'required_level' => 3, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/kompetensi-pegawai/{$siti->employee_id}", [
            'current_level' => [$competencyId => 3],
        ]);

        $response->assertRedirect(route('lms.admin.employee-competency.show', $siti->employee_id));
        $this->assertSame(3, DB::table('lms_employee_competencies')->where('employee_id', $siti->employee_id)->where('competency_id', $competencyId)->value('current_level'));
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/pelatihan/kompetensi');

        $response->assertForbidden();
    }

    private function seedCompetency(): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_competencies')->insert([
            'id' => $id, 'code' => 'COMP-'.uniqid(), 'name' => 'Kompetensi Uji',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
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
