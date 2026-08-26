<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Menu sidebar untuk "Pejabat SDM (Approver)" (hr_approver) dan
 * "Pimpinan Unit/Divisi atau KC/KCP" (pimpinan_kantor) SEKARANG
 * mengikuti `role_has_permissions` yang sama dipakai middleware rute
 * DAN halaman Peta Peran (RoleFeatureMapController) — bukan lagi
 * `hasRole()`/`hasAnyRole()` hardcode. Tes ini MEMBUKTIKAN itu: pindah
 * izin ke peran lain lewat data (persis yang dilakukan Peta Peran saat
 * admin klik simpan), TANPA ubah kode, lalu tautan sidebar ikut
 * berpindah.
 */
final class SidebarFollowsDynamicPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pindah_izin_dashboard_hc_ke_peran_lain_memindah_tautan_sidebar_tanpa_ubah_kode(): void
    {
        $auditor = $this->userWithNrp('2020.01.0231'); // auditor — TIDAK punya hc-dashboard.view secara default

        $before = $this->actingAs($auditor)->get(route('ess.dashboard'));
        $before->assertOk();
        $before->assertDontSeeText('Dashboard HC');

        $this->grantPermissionToRole('hc-dashboard.view', 'auditor');

        $after = $this->actingAs($auditor)->get(route('ess.dashboard'));
        $after->assertOk();
        $after->assertSeeText('Dashboard HC');
    }

    public function test_cabut_izin_pembayaran_lembur_hc_dari_hr_approver_menyembunyikan_tautannya(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061'); // hr_approver — punya overtime-disbursement.hc secara default

        $before = $this->actingAs($hrApprover)->get(route('ess.dashboard'));
        $before->assertOk();
        $before->assertSeeText('Pembayaran Lembur (Kantor Pusat)');

        $this->revokePermissionFromRole('overtime-disbursement.hc', 'hr_approver');

        $after = $this->actingAs($hrApprover)->get(route('ess.dashboard'));
        $after->assertOk();
        $after->assertDontSeeText('Pembayaran Lembur (Kantor Pusat)');
    }

    public function test_pindah_izin_antrean_tukar_shift_ke_hr_approver_memunculkan_tautannya(): void
    {
        // Nur Aisyah (hr_approver) di data contoh JUGA memegang peran
        // pimpinan_kantor — sengaja pilih izin yang TIDAK diberikan ke
        // hr_approver MAUPUN pimpinan_kantor/direktur_bidang (peran
        // lain yang dia pegang), supaya kemunculan tautan murni berkat
        // langkah grant di bawah, bukan peran lain yang kebetulan sudah
        // dia punya.
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $before = $this->actingAs($hrApprover)->get(route('ess.dashboard'));
        $before->assertOk();
        $before->assertDontSeeText('Antrean Tukar Shift');

        $this->grantPermissionToRole('shift-swap-approval.view', 'hr_approver');

        $after = $this->actingAs($hrApprover)->get(route('ess.dashboard'));
        $after->assertOk();
        $after->assertSeeText('Antrean Tukar Shift');
    }

    private function grantPermissionToRole(string $permissionName, string $roleName): void
    {
        $permissionId = DB::table('permissions')->where('name', $permissionName)->value('id');
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        $exists = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('role_id', $roleId)
            ->exists();

        if (! $exists) {
            DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }

        // Sama seperti RoleFeatureMapController::update() — tulis
        // langsung lewat DB::table() TIDAK memicu pembersihan cache
        // bawaan spatie/laravel-permission.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function revokePermissionFromRole(string $permissionName, string $roleName): void
    {
        $permissionId = DB::table('permissions')->where('name', $permissionName)->value('id');
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        DB::table('role_has_permissions')->where('permission_id', $permissionId)->where('role_id', $roleId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
