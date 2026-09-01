<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alasan penolakan — SEBELUM migrasi ini, TIDAK SATU PUN dari 6 tabel
 * pengajuan pegawai (Cuti/Lembur/SPPD/Izin/Tukar Shift/Pendaftaran
 * Pelatihan) punya kolom untuk menyimpan alasan penolakan sama sekali
 * (dikonfirmasi lewat audit menyeluruh) — pegawai yang ditolak hanya
 * melihat lencana status berwarna, tidak pernah tahu kenapa. Nama
 * kolom `decision_note` SENGAJA disamakan dengan kolom yang SUDAH ADA
 * (tapi mati, tidak pernah diisi UI) di emp_profile_change_requests,
 * supaya konsisten satu istilah di seluruh aplikasi.
 */
return new class extends Migration
{
    private const TABLES = [
        'leave_requests',
        'ovt_requests',
        'spd_requests',
        'izin_requests',
        'shf_swap_requests',
        'lms_enrollments',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->text('decision_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('decision_note');
            });
        }
    }
};
