<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch pembayaran SPPD Massal — dibayar per grup memo (spd_memo_groups),
 * bukan per divisi seperti Lembur (grup memo sendiri sudah jadi satuan
 * alami pembayaran, lihat ProcessSppdPaymentBatch). Pola tabel mengikuti
 * ovt_payment_batches/ovt_payment_batch_items TAPI TANPA kolom pajak/akun
 * pajak — SPPD tidak punya pemotongan pajak, Nota Debet dan Jurnal Slip
 * menampilkan angka yang sama (satu akun beban saja, reuse
 * fin_journal_accounts yang sudah ada sejak migrasi Lembur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spd_payment_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_number', 40)->unique();
            $table->uuid('memo_group_id');
            $table->string('payer_scope', 10); // snapshot dari grup memo — hc | branch
            $table->uuid('office_id')->nullable(); // snapshot dari grup memo
            $table->uuid('signatory_employee_id');
            $table->uuid('journal_expense_account_id');
            $table->string('currency', 3)->default('IDR');
            $table->bigInteger('total_amount_cents');
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('memo_group_id')->references('id')->on('spd_memo_groups')->restrictOnDelete();
            $table->foreign('office_id')->references('id')->on('md_offices')->nullOnDelete();
            $table->foreign('signatory_employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('journal_expense_account_id')->references('id')->on('fin_journal_accounts')->restrictOnDelete();
        });

        Schema::create('spd_payment_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->uuid('spd_request_id')->unique(); // no-double-pay
            $table->uuid('employee_id');
            $table->bigInteger('amount_cents');
            $table->string('bank_account_number', 30)->nullable();
            $table->timestampTz('created_at');

            $table->foreign('batch_id')->references('id')->on('spd_payment_batches')->cascadeOnDelete();
            $table->foreign('spd_request_id')->references('id')->on('spd_requests')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spd_payment_batch_items');
        Schema::dropIfExists('spd_payment_batches');
    }
};
