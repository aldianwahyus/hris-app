<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Lms\Application\ComputeTalentReadiness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Talent Management (BRD §5.6) — performance_score/potential_score
 * MANUAL (proksi, HRIS ini tidak punya sistem penilaian kinerja
 * historis); readiness_score DIHITUNG sistem (ComputeTalentReadiness).
 */
final class LmsTalentManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hc_dapat_mengisi_profil_talenta(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/talenta/{$siti->employee_id}", [
            'performance_score' => 4,
            'potential_score' => 5,
            'notes' => 'Catatan uji.',
        ]);

        $response->assertRedirect(route('lms.admin.talent.show', $siti->employee_id));
        $profile = DB::table('lms_talent_profiles')->where('employee_id', $siti->employee_id)->first();
        $this->assertSame(4, $profile->performance_score);
        $this->assertSame(5, $profile->potential_score);
    }

    public function test_readiness_score_berubah_saat_potential_score_diisi(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $compute = app(ComputeTalentReadiness::class);

        $this->assertNull($compute->forEmployee($siti->employee_id));

        $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/talenta/{$siti->employee_id}", [
            'performance_score' => 3,
            'potential_score' => 5,
        ]);

        $readiness = $compute->forEmployee($siti->employee_id);
        $this->assertNotNull($readiness);
        $this->assertEquals(1.0, $readiness); // hanya komponen potential (5/5=1.0) yang tersedia
    }

    public function test_readiness_score_naik_saat_gap_kompetensi_ditutup(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $positionId = DB::table('emp_employees')->where('id', $siti->employee_id)->value('position_id');
        $competencyId = (string) Uuid7::generate();

        DB::table('lms_competencies')->insert([
            'id' => $competencyId, 'code' => 'COMP-'.uniqid(), 'name' => 'Kompetensi Uji',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);
        DB::table('lms_position_competencies')->insert([
            'id' => (string) Uuid7::generate(), 'position_id' => $positionId, 'competency_id' => $competencyId,
            'required_level' => 4, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $compute = app(ComputeTalentReadiness::class);
        $before = $compute->forEmployee($siti->employee_id); // gap penuh, current_level default 0

        DB::table('lms_employee_competencies')->insert([
            'id' => (string) Uuid7::generate(), 'employee_id' => $siti->employee_id, 'competency_id' => $competencyId,
            'current_level' => 4, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $after = $compute->forEmployee($siti->employee_id);

        $this->assertNotNull($before);
        $this->assertNotNull($after);
        $this->assertGreaterThan($before, $after);
    }

    public function test_9box_mengelompokkan_pegawai_yang_sudah_dinilai(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        DB::table('lms_talent_profiles')->insert([
            'id' => (string) Uuid7::generate(), 'employee_id' => $siti->employee_id,
            'performance_score' => 5, 'potential_score' => 5,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($this->hrAdmin())->get('/admin/pelatihan/talenta');

        $response->assertOk();
        $response->assertSeeText('Siti Rahmawati');
    }

    public function test_hc_dapat_menambah_kandidat_suksesi(): void
    {
        $positionId = DB::table('md_positions')->where('code', 'OFC')->value('id');
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/suksesi', [
            'position_id' => $positionId,
            'candidate_employee_id' => $siti->employee_id,
            'readiness_level' => 'ready_1_2_years',
        ]);

        $response->assertRedirect(route('lms.admin.succession.index'));
        $this->assertSame(1, DB::table('lms_succession_plans')->where('position_id', $positionId)->where('candidate_employee_id', $siti->employee_id)->count());
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/pelatihan/talenta');

        $response->assertForbidden();
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
