<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Whistleblowing/Pengaduan (Fase 2) — permission baru, HANYA
 * hr_approver (DIKONFIRMASI user, BUKAN hr_admin — mencegah laporan
 * soal staf HC kantor tertentu dibaca HC kantor itu sendiri), lihat
 * WhistleblowingQueueController.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'whistleblowing.manage';

    private const ROLES = ['hr_approver'];

    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => self::PERMISSION_NAME,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id', 'name');

        foreach (self::ROLES as $roleName) {
            $roleId = $roleIds[$roleName] ?? null;

            if ($roleId === null) {
                continue;
            }

            DB::table('role_has_permissions')->insert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');

        if ($permissionId !== null) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
