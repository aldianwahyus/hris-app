<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dua permission BARU: dasbor Admin Cabang (hr_admin, lingkup OFFICE —
 * belum pernah ada dasbor untuk peran ini sebelumnya) dan laporan
 * Record Pegawai (riwayat posisi per bulan, SYSADMIN + Admin HC —
 * lihat migrasi emp_position_history untuk skemanya). Pola SAMA
 * PERSIS 2026_09_09_000003_seed_izin_permissions.php.
 */
return new class extends Migration
{
    /** @return array<int, array{0: string, 1: array<int, string>}> */
    private function permissionRoleMap(): array
    {
        return [
            ['branch-dashboard.view', ['hr_admin']],
            ['employee-position-record.view', ['system_admin', 'hr_approver']],
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
