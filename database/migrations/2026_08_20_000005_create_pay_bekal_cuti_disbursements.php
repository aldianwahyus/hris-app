<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bekal Cuti — uang saku dibayar SEKALI/TAHUN saat pegawai pertama
 * kali memakai jatah cuti tahun berjalan (bucket current_year, lihat
 * leave_balances.triggers_allowance & teks statis di ess/dashboard).
 * TIDAK ADA rumus resmi terverifikasi (beda dari Iuran/Lembur yang
 * punya SK eksplisit) — amount_cents SENGAJA nullable, diisi manual
 * saat pencairan (lihat DisburseBekalCuti), BUKAN dihitung otomatis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_bekal_cuti_disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('leave_request_id'); // pengajuan CT yang memicu (referensi, bukan basis hitung)
            $table->unsignedSmallInteger('year');
            $table->bigInteger('amount_cents')->nullable(); // diisi saat pencairan

            $table->string('status', 20)->default('pending'); // pending | disbursed
            $table->uuid('disbursed_by')->nullable();
            $table->timestampTz('disbursed_at')->nullable();
            $table->string('disbursement_reference', 100)->nullable();

            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->foreign('leave_request_id')->references('id')->on('leave_requests');
            $table->unique(['employee_id', 'year'], 'uq_bekal_cuti_employee_year');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_bekal_cuti_disbursements');
    }
};
