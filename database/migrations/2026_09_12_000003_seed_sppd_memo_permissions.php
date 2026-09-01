<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission baru untuk SPPD Massal (input memo + batch pembayaran) —
 * pola SAMA seed permission modul lain (mis.
 * 2026_09_09_000003_seed_izin_permissions.php). Terpisah dari
 * sppd-approval.* dan sppd-disbursement.* yang tetap eksklusif untuk
 * jalur SPPD mandiri lama, tidak disentuh sama sekali oleh fitur ini.
 */
return new class extends Migration
{
    /** @return array<int, array{0: string, 1: array<int, string>}> */
    private function permissionRoleMap(): array
    {
        return [
            ['sppd-memo.manage', ['hr_admin', 'hr_approver']],
            ['sppd-payment-batch.hc', ['hr_approver']],
            ['sppd-payment-batch.branch', ['hr_admin']],
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
