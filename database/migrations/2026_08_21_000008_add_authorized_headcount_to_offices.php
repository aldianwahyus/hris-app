<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formasi/kuota pegawai resmi PER KANTOR — dasar perhitungan GAP
 * (kuota − aktual) di Analitik SDM (HcDashboardController). nullable
 * SENGAJA: kantor yang belum ditetapkan formasinya harus tampil
 * "belum ditetapkan", BUKAN GAP palsu (0 − aktual) — lihat
 * OfficeFormasiController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('md_offices', function (Blueprint $table) {
            $table->unsignedSmallInteger('authorized_headcount')->nullable()->after('geofence_radius_m');
        });
    }

    public function down(): void
    {
        Schema::table('md_offices', function (Blueprint $table) {
            $table->dropColumn('authorized_headcount');
        });
    }
};
