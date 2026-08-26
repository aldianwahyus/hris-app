<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat Keputusan (SK) — Mutasi/Promosi/Sanksi/Lainnya, diinput
 * SYSADMIN/hr_admin, tulis LANGSUNG tanpa persetujuan (pola sama
 * emp_sanctions/emp_internal_work_histories dkk., lihat
 * ResolveEmployeeForHrAction).
 *
 * SK Mutasi/Promosi bisa MEMICU pengajuan perubahan data induk pegawai
 * lewat mekanisme maker-checker yang SUDAH ADA
 * (emp_profile_change_requests, tidak diubah) — profile_change_request_id
 * menyimpan tautannya kalau ada. SENGAJA tidak ada kolom status
 * tersendiri di sini: status persetujuannya dibaca lewat JOIN ke
 * emp_profile_change_requests saat tampil, supaya tidak ada dua sumber
 * kebenaran yang bisa basi (pelajaran take_home_partial_cents di modul
 * Payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_decision_letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('sk_type', 20); // mutasi|promosi|sanksi|lainnya
            $table->string('sk_number', 100);
            $table->date('sk_date');
            $table->date('effective_date')->nullable();
            $table->text('description');
            $table->string('document_path', 255)->nullable();
            $table->string('document_original_name', 255)->nullable();
            $table->uuid('profile_change_request_id')->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->foreign('profile_change_request_id')->references('id')->on('emp_profile_change_requests');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_decision_letters');
    }
};
