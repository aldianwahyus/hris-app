<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hak akses dinamis: `role_has_permissions` sekarang jadi sumber
 * kebenaran akses (dibaca middleware `permission:xxx`), dan halaman
 * "Peta Peran" bisa menulisnya lewat RoleFeatureMapController::update().
 * Lihat migrasi 2026_08_28_000001_seed_dynamic_permissions.php untuk
 * state awal 30 permission yang dites di sini.
 */
final class DynamicPermissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_memberi_izin_baru_ke_peran_membuka_rute_yang_tadinya_403(): void
    {
        // hr_approver (BUKAN auditor — lihat test_auditor_tidak_dapat_
        // diberi_izin_selain_lihat_saja di RoleFeatureMapTest untuk
        // pagar independensi Auditor §6.3, employee-directory.manage
        // bukan izin lihat-saja jadi tidak sah diberikan ke Auditor).
        // Payload WAJIB berisi seluruh izin yang SUDAH dipegang + izin
        // baru (grantedPermissionsPlus) — update() mengganti SELURUH
        // set izin peran itu, bukan menambah satu saja.
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->get('/pegawai');
        $response->assertForbidden();

        $this->actingAs($this->sysAdmin())
            ->post(route('sysadmin.role-map.update'), [
                'permissions' => ['hr_approver' => $this->grantedPermissionsPlus('hr_approver', 'employee-directory.manage')],
            ])
            ->assertRedirect(route('sysadmin.role-map.index'));

        $this->assertTrue($hrApprover->fresh()->hasPermissionTo('employee-directory.manage'));

        $response = $this->actingAs($hrApprover)->get('/pegawai');
        $response->assertOk();
    }

    public function test_mencabut_izin_dari_peran_menutup_akses(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302');

        $this->actingAs($hrAdmin)->get('/pegawai')->assertOk();

        $this->actingAs($this->sysAdmin())
            ->post(route('sysadmin.role-map.update'), [
                'permissions' => ['hr_admin' => $this->grantedPermissionsMinus('hr_admin', 'employee-directory.manage')],
            ])
            ->assertRedirect(route('sysadmin.role-map.index'));

        $this->assertFalse($hrAdmin->fresh()->hasPermissionTo('employee-directory.manage'));
        $this->actingAs($hrAdmin)->get('/pegawai')->assertForbidden();
    }

    public function test_aktor_ditolak_mengubah_izin_peran_yang_dipegangnya_sendiri(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)
            ->post(route('sysadmin.role-map.update'), [
                'permissions' => ['hr_approver' => ['payroll-deduction.manage']],
            ]);

        $response->assertForbidden();
        $this->assertFalse($hrApprover->fresh()->hasPermissionTo('payroll-deduction.manage'));
    }

    public function test_system_admin_dikecualikan_dari_pagar_dan_boleh_mengubah_izin_perannya_sendiri(): void
    {
        $sysAdmin = $this->sysAdmin();
        $this->assertTrue($sysAdmin->hasRole('system_admin'));

        $response = $this->actingAs($sysAdmin)
            ->post(route('sysadmin.role-map.update'), [
                'permissions' => ['system_admin' => $this->grantedPermissionsPlus('system_admin', 'employee-directory.manage')],
            ]);

        $response->assertRedirect(route('sysadmin.role-map.index'));
        $this->assertTrue($sysAdmin->fresh()->hasPermissionTo('employee-directory.manage'));
    }

    public function test_system_admin_boleh_mengubah_izin_peran_hr_approver(): void
    {
        $sysAdmin = $this->sysAdmin();
        $this->assertFalse($sysAdmin->hasRole('hr_approver'));

        $response = $this->actingAs($sysAdmin)
            ->post(route('sysadmin.role-map.update'), [
                'permissions' => ['hr_approver' => $this->grantedPermissionsPlus('hr_approver', 'payroll-deduction.manage')],
            ]);

        $response->assertRedirect(route('sysadmin.role-map.index'));

        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->assertTrue($hrApprover->fresh()->hasPermissionTo('payroll-deduction.manage'));
    }

    public function test_perubahan_izin_tercatat_di_audit_trail(): void
    {
        // hr_approver, bukan auditor — alasan sama seperti test di atas.
        $baseline = $this->currentPermissions('hr_approver');

        $this->actingAs($this->sysAdmin())
            ->post(route('sysadmin.role-map.update'), [
                'permissions' => ['hr_approver' => $this->grantedPermissionsPlus('hr_approver', 'employee-directory.manage')],
            ]);

        $grantAudit = DB::table('aud_change_logs')
            ->where('auditable_type', 'role_permission')
            ->where('action', 'permission_granted')
            ->get();
        $this->assertTrue($grantAudit->contains(fn ($row) => str_contains($row->new_values, 'employee-directory.manage')));

        $this->actingAs($this->sysAdmin())
            ->post(route('sysadmin.role-map.update'), [
                'permissions' => ['hr_approver' => $baseline],
            ]);

        $revokeAudit = DB::table('aud_change_logs')
            ->where('auditable_type', 'role_permission')
            ->where('action', 'permission_revoked')
            ->get();
        $this->assertTrue($revokeAudit->contains(fn ($row) => str_contains($row->old_values, 'employee-directory.manage')));
    }

    public function test_peran_lain_ditolak_dari_edit_peta_peran(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post(route('sysadmin.role-map.update'), [
                'permissions' => ['auditor' => ['employee-directory.manage']],
            ]);

        $response->assertForbidden();
    }

    public function test_manajemen_pengguna_dan_daftar_peta_peran_tetap_hardcode_tidak_terpengaruh_migrasi(): void
    {
        $this->actingAs($this->sysAdmin())->get('/admin/sistem/pengguna')->assertOk();
        $this->actingAs($this->sysAdmin())->get('/admin/sistem/peta-peran')->assertOk();
        $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/admin/sistem/peta-peran')->assertOk();
    }

    /** @return array<int, string> */
    private function grantedPermissionsPlus(string $roleName, string $permission): array
    {
        return array_values(array_unique([...$this->currentPermissions($roleName), $permission]));
    }

    /** @return array<int, string> */
    private function grantedPermissionsMinus(string $roleName, string $permission): array
    {
        return array_values(array_diff($this->currentPermissions($roleName), [$permission]));
    }

    /** @return array<int, string> */
    private function currentPermissions(string $roleName): array
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        return DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $roleId)
            ->pluck('permissions.name')
            ->all();
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
