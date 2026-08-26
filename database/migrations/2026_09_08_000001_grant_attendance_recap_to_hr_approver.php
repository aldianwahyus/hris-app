<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rekap Absensi tadinya hanya hr_admin (lingkup OFFICE, kantornya
 * sendiri) — ditambah hr_approver di sini supaya Admin HC bisa melihat
 * lingkup BANK_WIDE (kantor pusat seluruh divisi + seluruh cabang +
 * KCP), pola SAMA seperti overtime-recap.view yang sudah lebih dulu
 * dipegang kedua role (lihat AttendanceRecapController::resolveScope()).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'attendance-recap.view')->value('id');
        $roleId = DB::table('roles')->where('name', 'hr_approver')->value('id');

        if ($permissionId !== null && $roleId !== null) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)->where('role_id', $roleId)->exists();

            if (! $exists) {
                DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'attendance-recap.view')->value('id');
        $roleId = DB::table('roles')->where('name', 'hr_approver')->value('id');

        if ($permissionId !== null && $roleId !== null) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->where('role_id', $roleId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
