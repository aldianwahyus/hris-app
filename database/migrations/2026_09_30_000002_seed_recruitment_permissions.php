<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rekrutmen (ATS) — DUA permission: `recruitment.manage` (operasi
 * ATS sehari-hari: posting/kandidat/pipeline/wawancara/tawaran,
 * hr_admin+hr_approver) dan `recruitment-requisition.decide`
 * (checker requisition, HANYA hr_approver — pola PERSIS
 * payroll-approval.manage/employee-approval yang checker-only).
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private const PERMISSIONS = [
        'recruitment.manage' => ['hr_admin', 'hr_approver'],
        'recruitment-requisition.decide' => ['hr_approver'],
    ];

    public function up(): void
    {
        $now = now();
        $roleIds = DB::table('roles')->pluck('id', 'name');

        foreach (self::PERMISSIONS as $permissionName => $roles) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $permissionName,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($roles as $roleName) {
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
        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
