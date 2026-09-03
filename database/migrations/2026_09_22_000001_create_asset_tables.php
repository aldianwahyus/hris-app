<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manajemen Aset — modul baru (evaluasi PM/client 2026-09-02, dibanding
 * HRIS komersial). Katalog aset perusahaan (`ast_assets`) + riwayat
 * penugasan ke pegawai (`ast_assignments`, satu baris per kali
 * ditugaskan — `returned_at` NULL berarti masih dipegang pegawai itu
 * SEKARANG, dipakai Offboarding untuk membangun checklist "kembalikan
 * aset" otomatis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ast_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('asset_code', 30)->unique();
            $table->string('name', 150);
            $table->string('category', 50);
            $table->string('brand_model', 150)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->bigInteger('purchase_value_cents')->nullable();
            $table->string('condition', 20)->default('baik'); // baik | rusak_ringan | rusak_berat
            $table->string('status', 20)->default('tersedia'); // tersedia | dipakai | perbaikan | dihapuskan
            $table->uuid('office_id');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('office_id')->references('id')->on('md_offices')->restrictOnDelete();
            $table->index('status');
        });

        Schema::create('ast_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('asset_id');
            $table->uuid('employee_id');
            $table->timestampTz('assigned_at');
            $table->uuid('assigned_by');
            $table->timestampTz('returned_at')->nullable();
            $table->string('returned_condition', 20)->nullable(); // baik | rusak_ringan | rusak_berat
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('asset_id')->references('id')->on('ast_assets')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('assigned_by')->references('id')->on('emp_employees')->restrictOnDelete();
            // Dipakai Offboarding: "aset yang masih dipegang pegawai X" =
            // where employee_id=X AND returned_at IS NULL.
            $table->index(['employee_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ast_assignments');
        Schema::dropIfExists('ast_assets');
    }
};
