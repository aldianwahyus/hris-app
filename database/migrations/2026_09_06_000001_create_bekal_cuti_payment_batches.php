<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran Bekal Cuti MASSAL — pola PERSIS ovt_payment_batches/
 * ovt_payment_batch_items (lihat 2026_09_04_000001), TAPI pajaknya
 * PPh 21 TER (bukan flat %): tarif dicari dari GOLONGAN pegawai
 * (Pph21Golongan::fromStatus, sudah ada — dipakai payroll bulanan)
 * atas PENGHASILAN GABUNGAN (gaji kotor bulan berjalan + bekal cuti
 * yang akan dibayar) — tapi PAJAK YANG DIPOTONG hanya atas porsi
 * bekal cuti itu sendiri (gaji kotornya sendiri sudah/akan dipajaki
 * terpisah lewat payroll bulanan, modul ini tidak menyentuhnya).
 *
 * Beda struktural dari lembur: pay_bekal_cuti_disbursements.
 * amount_cents SELALU NULL sampai sekarang ("diisi saat pencairan",
 * tidak ada rumus baku) — jumlah bekal cuti BRUTO tetap diisi admin
 * per baris saat proses batch (bukan dihitung sistem), snapshot
 * gaji-kotor+golongan+tarif TER dicatat untuk audit.
 *
 * Tiga akun jurnal (bukan dua seperti lembur) karena struktur Nota
 * Debet cuti digabung jadi SATU dokumen (bukan Nota Debet + Jurnal
 * Slip terpisah seperti lembur): DEBET Beban Uang Cuti (=net) +
 * DEBET Beban PPh 21 (=pajak), KREDIT Rekening Pegawai/terlampir
 * (=net) + KREDIT Penampungan Pajak (=pajak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bkl_payment_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_number', 40)->unique();
            $table->string('payer_scope', 10); // hc | branch
            $table->uuid('office_id')->nullable();
            $table->string('division', 100)->nullable();
            $table->uuid('signatory_employee_id');
            $table->uuid('journal_leave_expense_account_id');
            $table->uuid('journal_tax_expense_account_id');
            $table->uuid('journal_tax_holding_account_id');
            $table->bigInteger('total_gross_cents');
            $table->bigInteger('total_tax_cents');
            $table->bigInteger('total_net_cents');
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('office_id')->references('id')->on('md_offices')->nullOnDelete();
            $table->foreign('signatory_employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('journal_leave_expense_account_id')->references('id')->on('fin_journal_accounts')->restrictOnDelete();
            $table->foreign('journal_tax_expense_account_id')->references('id')->on('fin_journal_accounts')->restrictOnDelete();
            $table->foreign('journal_tax_holding_account_id')->references('id')->on('fin_journal_accounts')->restrictOnDelete();
        });

        Schema::create('bkl_payment_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->uuid('bekal_cuti_disbursement_id')->unique();
            $table->uuid('employee_id');
            $table->bigInteger('gross_cents');
            // Snapshot — basis lengkap perhitungan TER, audit trail kalau
            // skala gaji/golongan/tarif berubah nanti (batch LAMA harus
            // tetap mencerminkan angka yang dipakai SAAT itu).
            $table->bigInteger('gaji_kotor_cents');
            $table->bigInteger('combined_income_cents');
            $table->string('pph21_golongan', 1); // A | B | C
            $table->decimal('tax_rate_percent', 5, 2);
            $table->bigInteger('tax_cents');
            $table->bigInteger('net_cents');
            $table->string('bank_account_number', 30)->nullable();
            $table->timestampTz('created_at');

            $table->foreign('batch_id')->references('id')->on('bkl_payment_batches')->cascadeOnDelete();
            $table->foreign('bekal_cuti_disbursement_id')->references('id')->on('pay_bekal_cuti_disbursements')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkl_payment_batch_items');
        Schema::dropIfExists('bkl_payment_batches');
    }
};
