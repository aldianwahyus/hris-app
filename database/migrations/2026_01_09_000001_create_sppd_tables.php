<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 6 — SPPD (Perjalanan Dinas), BPP/442/03/64/2026.
 *
 * pay_sppd_tariffs: matriks komponen × kategori perjalanan × jenjang
 * jabatan/pita radius, BERVERSI seperti pay_salary_scale/
 * pay_pph21_ter_rates — sengaja SATU tabel generik (bukan satu tabel
 * per komponen) agar mudah disesuaikan lewat layar Admin Sistem saat
 * BPP berubah, tanpa migrasi baru.
 *
 * spd_requests SENGAJA TIDAK memakai Workflow Engine (beda dari Cuti/
 * Lembur) — status keputusan langsung pada baris ini. Pengingat SLA
 * H-7/H-3 tidak diminta untuk SPPD; menambah Workflow Engine tanpa
 * kebutuhan nyata hanya menambah kerumitan tanpa manfaat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('md_positions', function (Blueprint $table) {
            // Jenjang tarif SPPD (BPP §II.B) — BERBEDA dari job_grade/
            // person_grade (Payroll). NULL = belum dipetakan, SubmitSppdRequest
            // menolak pengajuan sampai dipetakan (lihat migrasi seed).
            $table->string('sppd_jabatan_tier', 40)->nullable()->after('overtime_rate_class');
        });

        Schema::create('pay_sppd_tariffs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('component', 40);       // uang_makan | uang_saku | hotel_cap | ...
            $table->string('trip_category', 40);
            $table->string('jabatan_tier', 40)->nullable();  // NULL = Jarak Pendek (berbasis radius)
            $table->string('radius_band', 20)->nullable();   // hanya Jarak Pendek
            $table->string('currency', 3)->default('IDR');
            $table->bigInteger('amount_cents');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_document', 150)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->index(['component', 'trip_category', 'jabatan_tier', 'radius_band', 'effective_from'], 'idx_sppd_tariff_lookup');
        });

        Schema::create('spd_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 40)->unique();
            $table->uuid('employee_id');
            $table->string('trip_category', 40);
            $table->string('jabatan_tier', 40)->nullable();
            $table->string('radius_band', 20)->nullable();
            $table->string('destination', 200);
            $table->text('purpose');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('total_days');
            $table->string('currency', 3)->default('IDR');

            // Lumpsum — dihitung penuh oleh sistem (SppdLumpsumCalculator).
            $table->bigInteger('uang_makan_cents');
            $table->bigInteger('uang_saku_cents');

            // At-cost — RENCANA anggaran (divalidasi terhadap plafon bila
            // berlaku), BUKAN realisasi. Realisasi sesuai invoice adalah
            // tahap lanjutan, belum dibangun di sini (lihat DEC-37 untuk
            // pola serupa pada Lembur).
            $table->bigInteger('estimasi_hotel_cents')->nullable();
            $table->bigInteger('estimasi_angkutan_setempat_cents')->nullable();
            $table->bigInteger('estimasi_transportasi_tujuan_cents')->nullable();

            $table->string('status', 20)->default('pending');
            $table->uuid('approver_id')->nullable();
            $table->timestampTz('decided_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->index(['employee_id', 'status']);
            $table->index(['approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spd_requests');
        Schema::dropIfExists('pay_sppd_tariffs');

        Schema::table('md_positions', function (Blueprint $table) {
            $table->dropColumn('sppd_jabatan_tier');
        });
    }
};
