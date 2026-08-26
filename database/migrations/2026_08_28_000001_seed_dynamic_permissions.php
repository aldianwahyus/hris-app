<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mengisi tabel `permissions`/`role_has_permissions` (bagian
 * spatie/laravel-permission yang SUDAH ADA di skema tapi belum pernah
 * dipakai — hanya `roles`/`model_has_roles` yang dipakai sampai
 * sekarang) — menggantikan gerbang `role:xxx` hardcode di
 * routes/web.php dengan `permission:xxx` berbasis data, supaya
 * SYSTEM_ADMIN/hr_approver bisa memberi/mencabut akses fitur ke suatu
 * role lewat halaman admin TANPA deploy kode.
 *
 * Daftar di bawah SENGAJA mencerminkan APA ADANYA akses role:xxx yang
 * berlaku SEBELUM migrasi ini — tidak ada perubahan perilaku hari ini,
 * cuma representasinya pindah dari kode ke data (lihat plan). Dua
 * kelompok rute TETAP hardcode dan TIDAK di sini: rute manajemen
 * pengguna (sysadmin.users) dan rute Peta Peran (sysadmin.role-map,
 * kunci gerbang, lihat RoleFeatureMapController), dan reset-kata-sandi
 * (keamanan akun murni IT).
 */
return new class extends Migration
{
    /** @return array<int, array{0: string, 1: array<int, string>}> */
    private function permissionRoleMap(): array
    {
        return [
            ['overtime-approval.view', ['atasan_langsung', 'pimpinan_kantor', 'auditor']],
            ['overtime-approval.decide', ['atasan_langsung', 'pimpinan_kantor']],
            ['overtime-disbursement.hc', ['hr_approver']],
            ['leave-approval.view', ['atasan_langsung', 'pimpinan_kantor', 'auditor']],
            ['leave-approval.decide', ['atasan_langsung', 'pimpinan_kantor']],
            ['bekal-cuti-disbursement.hc', ['hr_approver']],
            ['payroll-approval.manage', ['hr_approver']],
            ['sppd-approval.view', ['atasan_langsung', 'pimpinan_kantor', 'auditor']],
            ['sppd-approval.decide', ['atasan_langsung', 'pimpinan_kantor']],
            ['shift-swap-approval.view', ['atasan_langsung', 'auditor']],
            ['shift-swap-approval.decide', ['atasan_langsung']],
            ['outside-attendance-approval.view', ['pimpinan_kantor', 'auditor']],
            ['outside-attendance-approval.decide', ['pimpinan_kantor']],
            ['sppd-disbursement.hc.view', ['hr_approver', 'auditor']],
            ['sppd-disbursement.hc.decide', ['hr_approver']],
            ['employee-approval.manage', ['hr_approver']],
            ['employee-directory.manage', ['hr_admin']],
            ['employee-records.manage', ['hr_admin', 'hr_approver', 'system_admin']],
            ['decision-letter.manage', ['hr_admin', 'hr_approver', 'system_admin']],
            ['attendance-recap.view', ['hr_admin']],
            ['payroll-deduction.manage', ['hr_admin']],
            ['overtime-recap.view', ['hr_admin', 'hr_approver']],
            ['overtime-disbursement.branch', ['hr_admin']],
            ['bekal-cuti-disbursement.branch', ['hr_admin']],
            ['sppd-disbursement.branch', ['hr_admin']],
            ['audit-log.view', ['auditor']],
            ['hc-dashboard.view', ['hr_approver']],
            ['org-chart.view', ['system_admin', 'hr_approver']],
            ['sysadmin-it.manage', ['system_admin']],
            ['sysadmin-content.manage', ['system_admin', 'hr_approver']],
        ];
    }

    public function up(): void
    {
        $now = now();
        $roleIds = DB::table('roles')->pluck('id', 'name');

        foreach ($this->permissionRoleMap() as [$name, $roleNames]) {
            // permissions.id (pola bawaan spatie/laravel-permission)
            // adalah bigint auto-increment, BUKAN uuid seperti tabel
            // domain lain di proyek ini (dikonfirmasi sama seperti
            // roles.id) — insertGetId(), bukan Uuid7::generate().
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($roleNames as $roleName) {
                $roleId = $roleIds[$roleName] ?? null;

                if ($roleId === null) {
                    continue;
                }

                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // Menulis role_has_permissions lewat DB::table() langsung TIDAK
        // memicu pembersihan cache bawaan spatie/laravel-permission
        // (default 24 jam) — tanpa baris ini, gerbang permission:xxx yang
        // baru dipindah dari role:xxx bisa tampak "tidak berlaku" di
        // lingkungan yang cache-nya sudah sempat terisi SEBELUM migrasi
        // ini (mis. sudah ada request masuk lebih dulu). Lihat juga
        // RoleFeatureMapController::update().
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = array_column($this->permissionRoleMap(), 0);

        DB::table('role_has_permissions')->whereIn(
            'permission_id',
            fn ($q) => $q->select('id')->from('permissions')->whereIn('name', $names),
        )->delete();

        DB::table('permissions')->whereIn('name', $names)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
