<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daftar Kantor — rujukan tunggal `md_offices`, SYSTEM_ADMIN/hr_approver
 * (permission:sysadmin-content.manage). Kantor TIDAK BISA DIHAPUS, hanya
 * dinonaktifkan (`is_active`) — lihat OfficeController.
 */
final class OfficeDirectoryAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_menambah_kantor(): void
    {
        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor', [
            'code' => 'KCP-UJI',
            'name' => 'KCP Uji',
            'office_type' => 'sub_branch',
            'timezone' => 'Asia/Makassar',
        ]);

        $response->assertRedirect(route('sysadmin.offices.index'));
        $office = DB::table('md_offices')->where('code', 'KCP-UJI')->first();
        $this->assertNotNull($office);
        $this->assertTrue((bool) $office->is_active);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'md_office')->where('auditable_id', $office->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($audit);
    }

    public function test_hr_approver_dapat_menambah_dan_mengubah_kantor(): void
    {
        $this->actingAs($this->userWithNrp('2014.02.0061'))->post('/admin/sistem/daftar-kantor', [
            'code' => 'KCP-UJI2',
            'name' => 'KCP Uji Dua',
            'office_type' => 'sub_branch',
            'timezone' => 'Asia/Makassar',
        ])->assertRedirect(route('sysadmin.offices.index'));

        $office = DB::table('md_offices')->where('code', 'KCP-UJI2')->first();

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post("/admin/sistem/daftar-kantor/{$office->id}", [
            'code' => $office->code,
            'name' => 'KCP Uji Dua (Diubah)',
            'office_type' => 'sub_branch',
            'timezone' => 'Asia/Makassar',
        ]);

        $response->assertRedirect(route('sysadmin.offices.index'));
        $this->assertSame('KCP Uji Dua (Diubah)', DB::table('md_offices')->where('id', $office->id)->value('name'));
    }

    /**
     * Celah ditemukan lewat evaluasi PM/client (2026-09-01): kode kantor
     * SEBELUMNYA tidak bisa diubah sama sekali (hanya kolom lain yang
     * bisa disunting inline) — sekarang bisa, selama tetap unik. Kode
     * BUKAN foreign key langsung (dipakai sebagai kunci pencarian SAAT
     * impor CSV pegawai baru saja, lihat ImportNewEmployeeRequests) —
     * mengubahnya tidak memutus tautan office_id pegawai yang sudah ada.
     */
    public function test_kode_kantor_dapat_diubah(): void
    {
        $office = DB::table('md_offices')->first();

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/daftar-kantor/{$office->id}", [
            'code' => 'KP-BARU',
            'name' => $office->name,
            'office_type' => $office->office_type,
            'timezone' => $office->timezone,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('sysadmin.offices.index'));
        $this->assertSame('KP-BARU', DB::table('md_offices')->where('id', $office->id)->value('code'));
    }

    public function test_kode_kantor_duplikat_ditolak_saat_diubah(): void
    {
        $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor', [
            'code' => 'KCP-UJI3',
            'name' => 'KCP Uji Tiga',
            'office_type' => 'sub_branch',
            'timezone' => 'Asia/Makassar',
        ]);

        $existingCode = DB::table('md_offices')->where('code', '!=', 'KCP-UJI3')->value('code');
        $officeToEdit = DB::table('md_offices')->where('code', 'KCP-UJI3')->first();

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/daftar-kantor/{$officeToEdit->id}", [
            'code' => $existingCode,
            'name' => $officeToEdit->name,
            'office_type' => $officeToEdit->office_type,
            'timezone' => $officeToEdit->timezone,
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame('KCP-UJI3', DB::table('md_offices')->where('id', $officeToEdit->id)->value('code'));
    }

    public function test_kode_kantor_duplikat_ditolak(): void
    {
        $existingCode = DB::table('md_offices')->value('code');

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor', [
            'code' => $existingCode,
            'name' => 'Duplikat',
            'office_type' => 'branch',
            'timezone' => 'Asia/Makassar',
        ]);

        $response->assertSessionHas('gagal');
    }

    public function test_kantor_tidak_dapat_menjadi_induk_dirinya_sendiri(): void
    {
        $office = DB::table('md_offices')->first();

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/daftar-kantor/{$office->id}", [
            'code' => $office->code,
            'name' => 'Uji',
            'office_type' => 'branch',
            'parent_office_id' => $office->id,
            'timezone' => 'Asia/Makassar',
        ]);

        $response->assertSessionHas('gagal');
    }

    public function test_menonaktifkan_kantor_tidak_mengubah_data_pegawai_yang_sudah_ada(): void
    {
        $office = DB::table('md_offices')->first();
        $employeeCountBefore = DB::table('emp_employees')->where('office_id', $office->id)->count();

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/daftar-kantor/{$office->id}", [
            'code' => $office->code,
            'name' => $office->name,
            'office_type' => $office->office_type,
            'timezone' => $office->timezone,
            // is_active TIDAK dikirim => nonaktif
        ]);

        $response->assertRedirect(route('sysadmin.offices.index'));
        $this->assertFalse((bool) DB::table('md_offices')->where('id', $office->id)->value('is_active'));
        $this->assertSame($employeeCountBefore, DB::table('emp_employees')->where('office_id', $office->id)->count());
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/daftar-kantor');

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
