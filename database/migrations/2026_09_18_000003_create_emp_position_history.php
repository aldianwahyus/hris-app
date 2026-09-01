<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat posisi pegawai — dasar laporan "Record Pegawai" (rincian
 * posisi terakhir per bulan, TANPA form input, murni laporan).
 *
 * TIDAK ADA tabel semacam ini sebelumnya — emp_employees HANYA
 * menyimpan status TERKINI (DecideEmployeeProfileChange::approve()
 * langsung UPDATE in-place), jadi "posisi pegawai X pada bulan M yang
 * sudah lewat" tidak bisa direkonstruksi dari data yang ada. Baris di
 * sini adalah SNAPSHOT lengkap (bukan hanya field yang berubah) tiap
 * kali office_id/position_id/person_grade/job_grade BENAR-BENAR
 * berubah lewat persetujuan (lihat hook baru di
 * DecideEmployeeProfileChange::approve()) — supaya laporan cukup
 * mengambil baris TERAKHIR dengan effective_from <= akhir-bulan-M,
 * pola sama seperti tabel tarif efektif-bertanggal (TerRateRepository/
 * SalaryScaleRepository) yang sudah dipakai luas di aplikasi ini.
 *
 * effective_from = tanggal PERSETUJUAN (bukan tanggal efektif/tmt_pangkat
 * pada SK) — konsisten dengan kapan sistem SUNGGUHAN menerapkan
 * perubahan itu ke emp_employees, JUJUR terhadap keterbatasan ini
 * (didokumentasikan juga di UI laporan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_position_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('office_id');
            $table->uuid('position_id');
            $table->unsignedTinyInteger('person_grade')->nullable();
            $table->unsignedTinyInteger('job_grade')->nullable();
            $table->date('effective_from');
            // SK pemicu bila ada — nullable karena perubahan posisi bisa
            // juga lewat jalur maker-checker biasa tanpa SK formal.
            $table->uuid('decision_letter_id')->nullable();
            $table->timestampTz('created_at');
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->foreign('office_id')->references('id')->on('md_offices')->restrictOnDelete();
            $table->foreign('position_id')->references('id')->on('md_positions')->restrictOnDelete();
            $table->foreign('decision_letter_id')->references('id')->on('emp_decision_letters')->nullOnDelete();
            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_position_history');
    }
};
