<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin dinamis untuk modul LMS — masuk sistem `role_has_permissions`
 * yang sudah ada (lihat migrasi 2026_08_28_000001_seed_dynamic_permissions.php),
 * BUKAN `role:xxx` hardcode baru — modul ini jadi bukti hidup pertama
 * sistem RBAC dinamis dipakai untuk FITUR BARU, bukan cuma migrasi
 * fitur lama. SYSTEM_ADMIN/hr_approver bisa mengubah peta ini nanti
 * lewat halaman "Peta Peran" tanpa deploy kode.
 */
return new class extends Migration
{
    /** @return array<int, array{0: string, 1: array<int, string>}> */
    private function permissionRoleMap(): array
    {
        return [
            ['lms-catalog.manage', ['hr_admin', 'hr_approver', 'system_admin']],
            ['lms-enrollment-approval.view', ['atasan_langsung', 'auditor']],
            ['lms-enrollment-approval.decide', ['atasan_langsung']],
        ];
    }

    public function up(): void
    {
        $now = now();
        $roleIds = DB::table('roles')->pluck('id', 'name');

        foreach ($this->permissionRoleMap() as [$name, $roleNames]) {
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
        // (default 24 jam) — tanpa baris ini, izin baru ini baru
        // berlaku di lingkungan yang sudah lama berjalan (cache sudah
        // sempat terisi SEBELUM migrasi ini) setelah cache kedaluwarsa
        // sendiri. Lihat juga RoleFeatureMapController::update().
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
