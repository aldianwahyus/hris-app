<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Konfigurasi parameter sistem (Admin Sistem, lingkup TEKNIS) — nilai
 * lama TIDAK PERNAH ditimpa/dihapus, hanya ditutup lalu digantikan
 * nilai baru (sejalan ParameterResolver §5.1: penghitungan historis
 * harus tetap dapat diverifikasi).
 */
final class SystemParameterConfigTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_melihat_daftar_parameter(): void
    {
        $response = $this->actingAs($this->sysAdmin())->get('/admin/sistem/parameter');

        $response->assertOk();
        $response->assertSeeText('OVT_DAILY_MAX_HOURS');
    }

    public function test_menambah_nilai_baru_menutup_nilai_lama_bukan_menimpanya(): void
    {
        $parameter = DB::table('cfg_parameters')->where('code', 'OVT_DAILY_MAX_HOURS')->first();
        $nilaiLamaId = DB::table('cfg_parameter_values')
            ->where('parameter_id', $parameter->id)->whereNull('effective_to')->value('id');

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/parameter/{$parameter->id}/nilai", [
            'value' => '5',
            'effective_from' => '2027-01-01',
            'source_document' => 'KEP/UJI/2027',
        ]);

        $response->assertRedirect(route('sysadmin.parameters.index'));
        $response->assertSessionHas('sukses');

        $nilaiLama = DB::table('cfg_parameter_values')->where('id', $nilaiLamaId)->first();
        $this->assertSame('2026-12-31', $nilaiLama->effective_to);

        $nilaiBaru = DB::table('cfg_parameter_values')
            ->where('parameter_id', $parameter->id)->whereNull('effective_to')->first();
        $this->assertSame('5', $nilaiBaru->value);
        $this->assertSame('KEP/UJI/2027', $nilaiBaru->source_document);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'cfg_parameter')->where('auditable_id', $parameter->id)
            ->where('action', 'parameter_value_added')->first();
        $this->assertNotNull($audit);
    }

    public function test_nilai_tidak_sesuai_tipe_integer_ditolak(): void
    {
        $parameter = DB::table('cfg_parameters')->where('code', 'OVT_DAILY_MAX_HOURS')->first();

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/parameter/{$parameter->id}/nilai", [
            'value' => 'bukan-angka',
            'effective_from' => '2027-01-01',
        ]);

        $response->assertStatus(422);

        // Tidak ada baris baru tersisip akibat nilai tak valid.
        $this->assertSame(
            1,
            DB::table('cfg_parameter_values')->where('parameter_id', $parameter->id)->count()
        );
    }

    public function test_tanggal_berlaku_sebelum_nilai_aktif_dimulai_ditolak(): void
    {
        $parameter = DB::table('cfg_parameters')->where('code', 'OVT_DAILY_MAX_HOURS')->first();

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/parameter/{$parameter->id}/nilai", [
            'value' => '5',
            'effective_from' => '2020-01-01', // sebelum 2026-07-01 (mulai nilai aktif)
        ]);

        $response->assertStatus(422);
    }

    public function test_riwayat_menampilkan_nilai_yang_masih_aktif(): void
    {
        $parameter = DB::table('cfg_parameters')->where('code', 'LEAVE_BLOCK_LEAVE_MIN')->first();

        $response = $this->actingAs($this->sysAdmin())->get("/admin/sistem/parameter/{$parameter->id}/riwayat");

        $response->assertOk();
        $response->assertSeeText('Aktif');
    }

    public function test_peran_lain_ditolak_dari_konfigurasi_parameter(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/parameter');

        $response->assertForbidden();
    }

    private function sysAdmin(): User
    {
        return $this->userWithNrp('SYSADMIN');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
