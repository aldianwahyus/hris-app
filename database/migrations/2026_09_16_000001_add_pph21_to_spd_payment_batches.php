<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPPD Massal ternyata DIKENAKAN PPh 21 (dibuktikan dari contoh Nota
 * Debet/Jurnal Slip resmi Bank NTB Syariah — angka net yang tercetak
 * hanya cocok kalau tarif TER diterapkan, PERSIS mekanisme
 * ProcessBekalCutiPaymentBatch) — migrasi
 * 2026_09_12_000002_create_spd_payment_batches.php sebelumnya keliru
 * mengasumsikan "SPPD tidak punya pemotongan pajak" (bug ditemukan
 * lewat perbandingan dengan dokumen resmi, bukan lewat kode).
 *
 * Pola kolom PERSIS bkl_payment_batches/bkl_payment_batch_items (lihat
 * 2026_09_06_000001_create_bekal_cuti_payment_batches.php) — TIGA akun
 * jurnal (beban lumpsum yang sudah ada + beban PPh21 + penampungan
 * pajak), bukan gabung satu Nota Debet seperti Bekal Cuti (SPPD tetap
 * Nota Debet + Jurnal Slip TERPISAH, pola Lembur — lihat bukti dokumen
 * resmi SPPD yang punya Jurnal Slip sendiri).
 *
 * Semua kolom baru NULLABLE — batch yang sudah ada SEBELUM migrasi ini
 * tidak punya nilai pajak (tidak dihitung ulang secara retroaktif),
 * kode cetak harus menoleransi null di situ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spd_payment_batches', function (Blueprint $table) {
            $table->uuid('journal_tax_expense_account_id')->nullable()->after('journal_expense_account_id');
            $table->uuid('journal_tax_account_id')->nullable()->after('journal_tax_expense_account_id');
            $table->bigInteger('total_tax_cents')->nullable()->after('total_amount_cents');
            $table->bigInteger('total_net_cents')->nullable()->after('total_tax_cents');

            $table->foreign('journal_tax_expense_account_id')->references('id')->on('fin_journal_accounts')->restrictOnDelete();
            $table->foreign('journal_tax_account_id')->references('id')->on('fin_journal_accounts')->restrictOnDelete();
        });

        Schema::table('spd_payment_batch_items', function (Blueprint $table) {
            // Snapshot basis perhitungan TER — audit trail kalau skala
            // gaji/golongan/tarif berubah nanti (batch LAMA harus tetap
            // mencerminkan angka yang dipakai SAAT itu), pola sama
            // bkl_payment_batch_items.
            $table->bigInteger('gaji_kotor_cents')->nullable();
            $table->bigInteger('combined_income_cents')->nullable();
            $table->string('pph21_golongan', 1)->nullable(); // A | B | C
            $table->decimal('tax_rate_percent', 5, 2)->nullable();
            $table->bigInteger('tax_cents')->nullable();
            $table->bigInteger('net_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('spd_payment_batch_items', function (Blueprint $table) {
            $table->dropColumn(['gaji_kotor_cents', 'combined_income_cents', 'pph21_golongan', 'tax_rate_percent', 'tax_cents', 'net_cents']);
        });

        Schema::table('spd_payment_batches', function (Blueprint $table) {
            $table->dropForeign(['journal_tax_expense_account_id']);
            $table->dropForeign(['journal_tax_account_id']);
            $table->dropColumn(['journal_tax_expense_account_id', 'journal_tax_account_id', 'total_tax_cents', 'total_net_cents']);
        });
    }
};
