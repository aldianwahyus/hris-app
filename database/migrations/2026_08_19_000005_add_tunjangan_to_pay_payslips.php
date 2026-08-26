<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyalin tunjangan_jabatan_cents/tunjangan_penyesuaian_cents dari
 * emp_employees ke tiap slip pada saat generate — payslip HARUS
 * merekam nilai SAAT ITU, bukan menunjuk balik ke data induk pegawai
 * yang bisa berubah setelah run dibuat (payslip historis tidak boleh
 * ikut berubah kalau tunjangan pegawai diedit belakangan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_payslips', function (Blueprint $table) {
            $table->bigInteger('tunjangan_jabatan_cents')->default(0)->after('imbalan_kerja_cents');
            $table->bigInteger('tunjangan_penyesuaian_cents')->default(0)->after('tunjangan_jabatan_cents');
        });
    }

    public function down(): void
    {
        Schema::table('pay_payslips', function (Blueprint $table) {
            $table->dropColumn(['tunjangan_jabatan_cents', 'tunjangan_penyesuaian_cents']);
        });
    }
};
