<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel Tarif Efektif Rata-rata (TER) PPh 21 Bulanan Pegawai Tetap —
 * PMK 168/2023, tiga golongan (A/B/C) berdasarkan status PTKP.
 *
 * BERVERSI seperti pay_salary_scale: peraturan pajak berubah lewat
 * PMK baru, bukan konstanta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            // Jumlah tanggungan (0-3) untuk menentukan status PTKP
            // (TK/K digabung status kawin) — belum ada riwayat data
            // sesungguhnya, default 0 (lihat migrasi seed).
            $table->unsignedTinyInteger('tanggungan')->default(0)->after('marital_status');
        });

        Schema::create('pay_pph21_ter_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('golongan', 1); // A | B | C
            $table->unsignedTinyInteger('step');
            $table->bigInteger('income_from_cents');
            $table->bigInteger('income_to_cents')->nullable(); // NULL = lapisan teratas ("ke atas")
            $table->decimal('rate_percent', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_document', 150)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->index(['golongan', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_pph21_ter_rates');

        Schema::table('emp_employees', function (Blueprint $table) {
            $table->dropColumn('tanggungan');
        });
    }
};
