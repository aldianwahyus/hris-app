<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin Tidak Masuk Bekerja — permission baru, pola SAMA PERSIS Tukar
 * Shift (izin-approval.view ke atasan_langsung+auditor, izin-approval.
 * decide HANYA atasan_langsung), lihat migrasi seed dasar
 * 2026_08_28_000001_seed_dynamic_permissions.php untuk pola aslinya.
 */
return new class extends Migration
{
    /** @return array<int, array{0: string, 1: array<int, string>}> */
    private function permissionRoleMap(): array
    {
        return [
            ['izin-approval.view', ['atasan_langsung', 'auditor']],
            ['izin-approval.decide', ['atasan_langsung']],
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
