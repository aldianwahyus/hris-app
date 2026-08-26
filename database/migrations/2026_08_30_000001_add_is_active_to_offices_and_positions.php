<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `md_offices`/`md_positions` sudah punya `deleted_at` (softDeletesTz)
 * TAPI tidak pernah dipakai — semua ~11 titik `DB::table('md_offices'/
 * 'md_positions')` di seluruh app (query builder mentah) TIDAK
 * memfilternya. Memakai `deleted_at` untuk "nonaktifkan" akan diam-diam
 * bocor ke tempat-tempat itu (kantor/jabatan "dihapus" tetap muncul di
 * mana-mana). `is_active` BARU ini SENGAJA kolom terpisah — pola sama
 * `shf_shift_patterns.is_active`/`lms_courses.is_active` — hanya
 * sedikit titik yang PERLU memfilternya (lihat OfficeController/
 * PositionController/SystemAdminEmployeeController/EmployeeImportController/
 * EmployeeDirectoryController), sisanya tidak tersentuh sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('md_offices', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('geofence_radius_m');
        });

        Schema::table('md_positions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('overtime_rate_class');
        });
    }

    public function down(): void
    {
        Schema::table('md_offices', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('md_positions', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
