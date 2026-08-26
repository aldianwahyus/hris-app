<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RoleFeatureMapTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_melihat_peta_peran(): void
    {
        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get('/admin/sistem/peta-peran');

        $response->assertOk();
        $response->assertSeeText('Peta peran');
    }

    public function test_admin_hc_dapat_melihat_peta_peran(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/admin/sistem/peta-peran');

        $response->assertOk();
    }

    public function test_peran_lain_ditolak_dari_peta_peran(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/admin/sistem/peta-peran');

        $response->assertForbidden();
    }

    /**
     * Bug ditemukan lewat audit kode, diperbaiki hari ini: halaman ini
     * menulis role_has_permissions LANGSUNG, jalur yang tidak pernah
     * melewati SegregationOfDutyPolicy (yang hanya menjaga peran×peran,
     * bukan peran×izin) — tanpa pagar ini, siapa pun yang bisa membuka
     * halaman ini bisa diam-diam menjadikan Auditor pemutus/pencair,
     * meruntuhkan independensinya (§6.3).
     */
    public function test_auditor_tidak_dapat_diberi_izin_selain_lihat_saja(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $granted = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('roles.name', 'auditor')
            ->pluck('permissions.name')->all();

        $response = $this->actingAs($sysAdmin)->post(route('sysadmin.role-map.update'), [
            'permissions' => ['auditor' => [...$granted, 'payroll-approval.manage']],
        ]);

        $response->assertStatus(422);
        $this->assertFalse(
            DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
                ->where('roles.name', 'auditor')->where('permissions.name', 'payroll-approval.manage')
                ->exists(),
        );
    }

    public function test_auditor_tetap_dapat_diberi_izin_lihat_saja(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $granted = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('roles.name', 'auditor')
            ->pluck('permissions.name')->all();

        $response = $this->actingAs($sysAdmin)->post(route('sysadmin.role-map.update'), [
            'permissions' => ['auditor' => [...$granted, 'attendance-recap.view']],
        ]);

        $response->assertRedirect(route('sysadmin.role-map.index'));
        $this->assertTrue(
            DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
                ->where('roles.name', 'auditor')->where('permissions.name', 'attendance-recap.view')
                ->exists(),
        );
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
