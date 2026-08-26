<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tunjangan Jabatan dan Tunjangan Penyesuaian — TIDAK seperti Imbalan
 * Kerja (dihitung dari pay_salary_scale by person_grade+salary_step),
 * kedua komponen ini TIDAK punya tabel/kriteria baku (diverifikasi
 * terhadap data riil Bank NTB Syariah: Tunjangan Jabatan condong
 * mengikuti judul jabatan tapi terlalu banyak pengecualian untuk
 * dijadikan rumus otomatis; Tunjangan Penyesuaian murni individual,
 * tanpa pola sama sekali). Karena itu keduanya field EDITABLE pada
 * data induk pegawai, lewat maker-checker yang SUDAH ADA (Admin SDM
 * usul → Pejabat SDM setujui, lihat EditableEmployeeField), BUKAN
 * dihitung otomatis — sama seperti person_grade/job_grade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->bigInteger('tunjangan_jabatan_cents')->default(0)->after('job_grade');
            $table->bigInteger('tunjangan_penyesuaian_cents')->default(0)->after('tunjangan_jabatan_cents');
        });
    }

    public function down(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->dropColumn(['tunjangan_jabatan_cents', 'tunjangan_penyesuaian_cents']);
        });
    }
};
