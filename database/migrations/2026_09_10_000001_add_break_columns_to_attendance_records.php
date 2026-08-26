<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absen Istirahat/Kembali — dua kejadian baru DI ANTARA masuk dan
 * pulang (bukan tabel terpisah, kolom tambahan pada baris harian yang
 * sama, pola SAMA PERSIS check_in_at/check_out_at). OPSIONAL: pegawai
 * boleh langsung Masuk→Pulang tanpa pernah mencatat istirahat — lihat
 * RecordGpsAttendance untuk urutan yang ditegakkan (begitu Istirahat
 * dicatat, Pulang diblokir sampai Kembali dicatat juga).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('att_attendance_records', function (Blueprint $table) {
            $table->timestampTz('break_start_at')->nullable()->after('check_in_lng');
            $table->string('break_start_source', 20)->nullable()->after('break_start_at');
            $table->decimal('break_start_lat', 10, 7)->nullable()->after('break_start_source');
            $table->decimal('break_start_lng', 10, 7)->nullable()->after('break_start_lat');

            $table->timestampTz('break_end_at')->nullable()->after('break_start_lng');
            $table->string('break_end_source', 20)->nullable()->after('break_end_at');
            $table->decimal('break_end_lat', 10, 7)->nullable()->after('break_end_source');
            $table->decimal('break_end_lng', 10, 7)->nullable()->after('break_end_lat');
        });
    }

    public function down(): void
    {
        Schema::table('att_attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'break_start_at', 'break_start_source', 'break_start_lat', 'break_start_lng',
                'break_end_at', 'break_end_source', 'break_end_lat', 'break_end_lng',
            ]);
        });
    }
};
