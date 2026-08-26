<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4 jenis data riwayat self-report (Pelatihan, Sertifikasi, Organisasi,
 * Penghargaan) — pegawai input SENDIRI lewat CV Saya, tulis LANGSUNG
 * tanpa persetujuan (pola sama field pribadi CV Saya yang sudah ada,
 * lihat UpdateOwnPersonalDetails). Tidak ada created_by — employee_id
 * sudah cukup sebagai pemilik+pembuat (beda dari 5 tabel HR-only yang
 * aktornya staf HR, bukan si pegawai sendiri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_trainings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('training_name', 200);
            $table->string('organizer', 150)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('emp_certifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('certification_name', 200);
            $table->string('issuer', 150)->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('emp_organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('organization_name', 200);
            $table->string('role', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('emp_awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('award_name', 200);
            $table->string('issuer', 150)->nullable();
            $table->date('award_date')->nullable();
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_awards');
        Schema::dropIfExists('emp_organizations');
        Schema::dropIfExists('emp_certifications');
        Schema::dropIfExists('emp_trainings');
    }
};
