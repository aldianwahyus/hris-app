<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 4 jenis data riwayat self-report (Pelatihan, Sertifikasi, Organisasi,
 * Penghargaan) — pegawai kelola SENDIRI lewat CV Saya, tulis LANGSUNG
 * tanpa persetujuan. Satu file mencakup keempatnya (bentuk identik).
 */
final class EmployeeSelfReportRecordsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_menambah_pelatihan_miliknya_sendiri(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($siti)->post('/cv-saya/pelatihan', [
            'training_name' => 'Pelatihan Perbankan Syariah',
            'organizer' => 'LPPI',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-03',
        ]);

        $response->assertRedirect(route('ess.cv'));
        $response->assertSessionHas('sukses');

        $row = DB::table('emp_trainings')->where('employee_id', $sitiId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Pelatihan Perbankan Syariah', $row->training_name);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'employee_training')->where('action', 'created')->first();
        $this->assertNotNull($audit);

        $this->assertSame(0, DB::table('emp_profile_change_requests')->where('employee_id', $sitiId)->count());
    }

    public function test_pegawai_dapat_menghapus_pelatihan_miliknya_sendiri(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $sitiId = $this->employeeId('2018.03.0142');
        $trainingId = $this->seedTraining($sitiId);

        $response = $this->actingAs($siti)->delete("/cv-saya/pelatihan/{$trainingId}");

        $response->assertRedirect(route('ess.cv'));
        $this->assertSame(0, DB::table('emp_trainings')->where('id', $trainingId)->count());
    }

    public function test_pegawai_tidak_bisa_menghapus_pelatihan_milik_pegawai_lain(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $ahmadId = $this->employeeId('2015.07.0088');
        $trainingId = $this->seedTraining($ahmadId);

        $response = $this->actingAs($siti)->delete("/cv-saya/pelatihan/{$trainingId}");

        $response->assertNotFound();
        $this->assertSame(1, DB::table('emp_trainings')->where('id', $trainingId)->count());
    }

    public function test_pegawai_dapat_menambah_sertifikasi(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($siti)->post('/cv-saya/sertifikasi', [
            'certification_name' => 'Sertifikasi Manajemen Risiko',
            'issuer' => 'BSMR',
            'certificate_number' => 'CERT-0001',
        ]);

        $response->assertRedirect(route('ess.cv'));
        $this->assertSame(1, DB::table('emp_certifications')->where('employee_id', $sitiId)->count());
    }

    public function test_pegawai_dapat_menambah_dan_menghapus_organisasi(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $sitiId = $this->employeeId('2018.03.0142');

        $this->actingAs($siti)->post('/cv-saya/organisasi', [
            'organization_name' => 'IBI',
            'role' => 'Anggota',
        ]);

        $orgId = DB::table('emp_organizations')->where('employee_id', $sitiId)->value('id');
        $this->assertNotNull($orgId);

        $response = $this->actingAs($siti)->delete("/cv-saya/organisasi/{$orgId}");
        $response->assertRedirect(route('ess.cv'));
        $this->assertSame(0, DB::table('emp_organizations')->where('id', $orgId)->count());
    }

    public function test_pegawai_dapat_menambah_penghargaan(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($siti)->post('/cv-saya/penghargaan', [
            'award_name' => 'Pegawai Teladan',
            'issuer' => 'Bank NTB Syariah',
            'award_date' => '2026-01-01',
        ]);

        $response->assertRedirect(route('ess.cv'));
        $row = DB::table('emp_awards')->where('employee_id', $sitiId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Pegawai Teladan', $row->award_name);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'employee_award')->where('action', 'created')->first();
        $this->assertNotNull($audit);
    }

    public function test_cv_saya_menampilkan_daftar_riwayat_self_report(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $sitiId = $this->employeeId('2018.03.0142');
        $this->seedTraining($sitiId);

        $response = $this->actingAs($siti)->get('/cv-saya');

        $response->assertOk();
        $response->assertSeeText('Pelatihan yang pernah diikuti');
        $response->assertSeeText('Sertifikasi yang pernah diikuti');
        $response->assertSeeText('Organisasi yang pernah diikuti');
        $response->assertSeeText('Penghargaan yang pernah diterima');
    }

    private function seedTraining(string $employeeId): string
    {
        $id = (string) Uuid7::generate();

        DB::table('emp_trainings')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'training_name' => 'Pelatihan Uji',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
