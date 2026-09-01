<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Memberi akses ke peran TAMBAHAN untuk 2 permission yang SUDAH ADA
 * (bukan permission baru — beda dari 2026_09_18_000001) — Dashboard
 * HC (`hc-dashboard.view`, sebelumnya hanya hr_approver) dan Log Audit
 * (`audit-log.view`, sebelumnya hanya auditor) sekarang juga terbuka
 * untuk system_admin, dan Log Audit juga untuk hr_approver — sesuai
 * permintaan "Dashboard & Audit Trail untuk SYSADMIN dan Admin HC".
 * Grant LAMA (hr_approver pada hc-dashboard.view, auditor pada
 * audit-log.view) TIDAK dicabut — murni menambah, pola sama
 * `org-chart.view` yang sejak awal sudah dua peran.
 *
 * insertOrIgnore() (bukan insert()) — role_has_permissions punya
 * primary key komposit (permission_id, role_id); aman dijalankan
 * ulang tanpa duplicate-key error kalau kombinasi itu SUDAH ada
 * (mis. lewat Peta Peran sebelum migrasi ini jalan).
 */
return new class extends Migration
{
    /** @return array<int, array{0: string, 1: array<int, string>}> */
    private function grants(): array
    {
        return [
            ['hc-dashboard.view', ['system_admin']],
            ['audit-log.view', ['system_admin', 'hr_approver']],
        ];
    }

    public function up(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach ($this->grants() as [$permissionName, $roleNames]) {
            $permissionId = $permissionIds[$permissionName] ?? null;

            if ($permissionId === null) {
                continue;
            }

            foreach ($roleNames as $roleName) {
                $roleId = $roleIds[$roleName] ?? null;

                if ($roleId === null) {
                    continue;
                }

                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach ($this->grants() as [$permissionName, $roleNames]) {
            $permissionId = $permissionIds[$permissionName] ?? null;

            if ($permissionId === null) {
                continue;
            }

            $roleIdsToRemove = array_filter(array_map(fn ($name) => $roleIds[$name] ?? null, $roleNames));

            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->whereIn('role_id', $roleIdsToRemove)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
