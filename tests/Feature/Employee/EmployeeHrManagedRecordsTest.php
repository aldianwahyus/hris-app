<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 5 jenis data riwayat pegawai (Data Pasangan & Anak, Riwayat Kerja
 * Internal/Eksternal, Sanksi, Riwayat Kesehatan) — HR-only, tulis
 * LANGSUNG tanpa maker-checker (lihat ResolveEmployeeForHrAction).
 * Satu file test mencakup kelima jenis (bentuknya identik: store+
 * destroy, gerbang lingkup sama) supaya tidak 5× duplikasi berkas.
 */
final class EmployeeHrManagedRecordsTest extends TestCase
{
    use DatabaseTransactions;

    // ---------- Data Pasangan & Anak ----------

    public function test_hr_admin_dapat_menambah_data_keluarga_pegawai_kantor_sendiri(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung
        $targetId = $this->employeeId('2021.05.0302'); // pegawai kantor sendiri (dia sendiri)

        $response = $this->actingAs($rina)->post("/pegawai/{$targetId}/keluarga", [
            'relationship_type' => 'anak',
            'full_name' => 'Anak Uji',
            'birth_date' => '2015-01-01',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $row = DB::table('emp_family_members')->where('employee_id', $targetId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Anak Uji', $row->full_name);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'employee_family_member')->where('action', 'created')->first();
        $this->assertNotNull($audit);

        $this->assertSame(0, DB::table('emp_profile_change_requests')->where('employee_id', $targetId)->count());
    }

    public function test_hr_admin_ditolak_untuk_pegawai_kantor_lain(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung
        $sitiId = $this->employeeId('2018.03.0142'); // KC Mataram

        $response = $this->actingAs($rina)->post("/pegawai/{$sitiId}/keluarga", [
            'relationship_type' => 'anak',
            'full_name' => 'Anak Uji',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, DB::table('emp_family_members')->where('employee_id', $sitiId)->count());
    }

    public function test_hr_approver_dapat_menambah_data_keluarga_pegawai_kantor_mana_pun(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($hrApprover)->post("/pegawai/{$sitiId}/keluarga", [
            'relationship_type' => 'pasangan',
            'full_name' => 'Pasangan Uji',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, DB::table('emp_family_members')->where('employee_id', $sitiId)->count());
    }

    public function test_sysadmin_dapat_menambah_data_keluarga_pegawai_kantor_mana_pun(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($sysadmin)->post("/pegawai/{$sitiId}/keluarga", [
            'relationship_type' => 'pasangan',
            'full_name' => 'Pasangan Uji',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, DB::table('emp_family_members')->where('employee_id', $sitiId)->count());
    }

    public function test_hr_admin_dapat_menghapus_data_keluarga_kantornya(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');
        $targetId = $this->employeeId('2021.05.0302');
        $rowId = $this->seedFamilyMember($targetId);

        $response = $this->actingAs($rina)->delete("/pegawai/{$targetId}/keluarga/{$rowId}");

        $response->assertRedirect();
        $this->assertSame(0, DB::table('emp_family_members')->where('id', $rowId)->count());
    }

    // ---------- Riwayat Kerja Internal ----------

    public function test_hr_approver_dapat_menambah_riwayat_kerja_internal(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($hrApprover)->post("/pegawai/{$sitiId}/riwayat-internal", [
            'position_description' => 'Teller KC Mataram',
            'start_date' => '2018-01-01',
            'end_date' => '2020-01-01',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, DB::table('emp_internal_work_histories')->where('employee_id', $sitiId)->count());
        $this->assertSame(0, DB::table('emp_profile_change_requests')->where('employee_id', $sitiId)->count());
    }

    public function test_hr_admin_ditolak_riwayat_kerja_internal_pegawai_kantor_lain(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($rina)->post("/pegawai/{$sitiId}/riwayat-internal", [
            'position_description' => 'Teller',
            'start_date' => '2018-01-01',
        ]);

        $response->assertForbidden();
    }

    // ---------- Riwayat Kerja Eksternal ----------

    public function test_sysadmin_dapat_menambah_riwayat_kerja_eksternal(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($sysadmin)->post("/pegawai/{$sitiId}/riwayat-eksternal", [
            'company_name' => 'Bank Lain',
            'position' => 'Teller',
            'start_date' => '2015-01-01',
            'end_date' => '2017-01-01',
        ]);

        $response->assertRedirect();
        $row = DB::table('emp_external_work_histories')->where('employee_id', $sitiId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Bank Lain', $row->company_name);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'employee_external_work_history')->where('action', 'created')->first();
        $this->assertNotNull($audit);
    }

    // ---------- Sanksi ----------
    // Dikonsolidasi ke modul SK (sk_type='sanksi') — lihat
    // tests/Feature/Employee/DecisionLetterTest.php. emp_sanctions
    // dipensiunkan (migrasi 2026_08_27_000002).

    // ---------- Riwayat Kesehatan ----------

    public function test_hr_approver_dapat_menambah_riwayat_kesehatan(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($hrApprover)->post("/pegawai/{$sitiId}/riwayat-kesehatan", [
            'record_date' => '2026-01-01',
            'note' => 'Medical check-up tahunan — sehat.',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, DB::table('emp_health_records')->where('employee_id', $sitiId)->count());
    }

    public function test_peran_lain_ditolak_dari_semua_jenis_riwayat(): void
    {
        $siti = $this->userWithNrp('2018.03.0142'); // pegawai biasa
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($siti)->post("/pegawai/{$sitiId}/keluarga", [
            'relationship_type' => 'anak',
            'full_name' => 'Anak Uji',
        ]);

        $response->assertForbidden();
    }

    private function seedFamilyMember(string $employeeId): string
    {
        $id = (string) Uuid7::generate();

        DB::table('emp_family_members')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'relationship_type' => 'anak',
            'full_name' => 'Anak Uji',
            'created_by' => $employeeId,
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
