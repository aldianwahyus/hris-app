<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daftar Jabatan — rujukan tunggal `md_positions`, SYSTEM_ADMIN/hr_approver
 * (permission:sysadmin-content.manage). Jabatan TIDAK BISA DIHAPUS, hanya
 * dinonaktifkan (`is_active`) — lihat PositionController.
 */
final class PositionDirectoryAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_menambah_jabatan(): void
    {
        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-jabatan', [
            'code' => 'JAB-UJI',
            'name' => 'Jabatan Uji',
            'classification' => 'support',
            'job_grade_min' => 3,
            'job_grade_max' => 6,
        ]);

        $response->assertRedirect(route('sysadmin.positions.index'));
        $position = DB::table('md_positions')->where('code', 'JAB-UJI')->first();
        $this->assertNotNull($position);
        $this->assertTrue((bool) $position->is_active);
        $this->assertTrue((bool) $position->eligible_overtime_regular); // default true saat dibuat

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'md_position')->where('auditable_id', $position->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($audit);
    }

    public function test_hr_approver_dapat_menambah_jabatan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post('/admin/sistem/daftar-jabatan', [
            'code' => 'JAB-UJI2',
            'name' => 'Jabatan Uji Dua',
        ]);

        $response->assertRedirect(route('sysadmin.positions.index'));
        $this->assertSame(1, DB::table('md_positions')->where('code', 'JAB-UJI2')->count());
    }

    public function test_kode_jabatan_duplikat_ditolak(): void
    {
        $existingCode = DB::table('md_positions')->value('code');

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-jabatan', [
            'code' => $existingCode,
            'name' => 'Duplikat',
        ]);

        $response->assertSessionHas('gagal');
    }

    public function test_menonaktifkan_jabatan_dapat_diubah_kembali_aktif(): void
    {
        $position = DB::table('md_positions')->first();

        $this->actingAs($this->sysAdmin())->post("/admin/sistem/daftar-jabatan/{$position->id}", [
            'name' => $position->name,
        ]);
        $this->assertFalse((bool) DB::table('md_positions')->where('id', $position->id)->value('is_active'));

        $this->actingAs($this->sysAdmin())->post("/admin/sistem/daftar-jabatan/{$position->id}", [
            'name' => $position->name,
            'is_active' => '1',
        ]);
        $this->assertTrue((bool) DB::table('md_positions')->where('id', $position->id)->value('is_active'));
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/daftar-jabatan');

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
