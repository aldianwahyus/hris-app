<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pembayaran Lembur Massal — rombak dari satu-per-satu (DisburseOvertimePayment
 * lama, tetap ada untuk kompatibilitas riwayat) jadi batch: satu SPKL BARU
 * per batch (terpisah dari SPKL pengajuan individual di ovt_requests),
 * breakdown gross/pajak/net per pegawai (ovt_payment_batch_items), dan
 * dua akun jurnal terpilih dari daftar yang bisa dikelola HC
 * (fin_journal_accounts) — TIDAK ADA konsep jurnal/COA di app ini
 * sebelum migrasi ini, modul baru sepenuhnya.
 *
 * OVT_TAX_RATE_PERCENT (parameter baru, di-seed sekaligus di sini) —
 * tarif PLACEHOLDER, BELUM ada SK resmi (beda dari parameter Overtime
 * lain yang semua bersandar SK KEP/1827/06/64/2026) — wajib disesuaikan
 * admin lewat Konfigurasi Parameter sebelum pembayaran pertama
 * sungguhan, source_document ditandai eksplisit sebagai placeholder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_journal_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('category', 30); // beban | penampungan_pajak
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->integer('version')->default(1);
        });

        Schema::create('ovt_payment_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('spkl_number', 40)->unique();
            $table->string('payer_scope', 10); // hc | branch
            $table->uuid('office_id')->nullable();
            $table->string('division', 100)->nullable();
            $table->uuid('signatory_employee_id');
            $table->uuid('journal_expense_account_id');
            $table->uuid('journal_tax_account_id');
            $table->decimal('tax_rate_percent', 5, 2);
            $table->bigInteger('total_gross_cents');
            $table->bigInteger('total_tax_cents');
            $table->bigInteger('total_net_cents');
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('office_id')->references('id')->on('md_offices')->nullOnDelete();
            $table->foreign('signatory_employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('journal_expense_account_id')->references('id')->on('fin_journal_accounts')->restrictOnDelete();
            $table->foreign('journal_tax_account_id')->references('id')->on('fin_journal_accounts')->restrictOnDelete();
        });

        Schema::create('ovt_payment_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->uuid('ovt_request_id')->unique();
            $table->uuid('employee_id');
            $table->bigInteger('gross_cents');
            $table->bigInteger('tax_cents');
            $table->bigInteger('net_cents');
            $table->string('bank_account_number', 30)->nullable();
            $table->timestampTz('created_at');

            $table->foreign('batch_id')->references('id')->on('ovt_payment_batches')->cascadeOnDelete();
            $table->foreign('ovt_request_id')->references('id')->on('ovt_requests')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
        });

        $parameterId = (string) Str::uuid();

        DB::table('cfg_parameters')->insert([
            'id' => $parameterId,
            'code' => 'OVT_TAX_RATE_PERCENT',
            'name' => 'Tarif pajak pembayaran lembur (flat)',
            'value_type' => 'decimal',
            'unit' => 'persen',
            'owner_module' => 'Overtime',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        DB::table('cfg_parameter_values')->insert([
            'id' => (string) Str::uuid(),
            'parameter_id' => $parameterId,
            'value' => '5.00',
            'effective_from' => now()->format('Y-m-d'),
            'effective_to' => null,
            // Dikonfirmasi 5% flat oleh pengguna aplikasi (2026-08-21) —
            // sebelumnya placeholder, sekarang tarif resmi. Kalau ada SK
            // tertulis nanti, tambahkan nilai baru lewat Konfigurasi
            // Parameter (bukan menimpa baris ini) — lihat SystemParameterController::addValue().
            'source_document' => 'Dikonfirmasi 5% flat oleh pengguna aplikasi (2026-08-21)',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    public function down(): void
    {
        DB::table('cfg_parameter_values')->whereIn('parameter_id', DB::table('cfg_parameters')->where('code', 'OVT_TAX_RATE_PERCENT')->pluck('id'))->delete();
        DB::table('cfg_parameters')->where('code', 'OVT_TAX_RATE_PERCENT')->delete();

        Schema::dropIfExists('ovt_payment_batch_items');
        Schema::dropIfExists('ovt_payment_batches');
        Schema::dropIfExists('fin_journal_accounts');
    }
};
