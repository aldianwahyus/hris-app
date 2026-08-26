<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talent Management (BRD §5.6). `performance_score` di
 * `lms_talent_profiles` adalah INPUT MANUAL HC (proksi — HRIS ini
 * TIDAK punya sistem penilaian kinerja historis sama sekali, dicek
 * langsung ke seluruh migrations sebelum menulis ini). `readiness_score`
 * SEBALIKNYA benar-benar dihitung sistem (lihat ComputeTalentReadiness)
 * dari data yang sudah ada (capaian kompetensi §5.1 + progres learning
 * path §5.2 + potential_score manual ini) — makanya TIDAK disimpan
 * sebagai kolom di sini, dihitung ulang tiap ditampilkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_talent_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id')->unique();
            $table->unsignedTinyInteger('performance_score')->nullable();
            $table->unsignedTinyInteger('potential_score')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('assessed_by')->nullable();
            $table->timestampTz('assessed_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
        });

        Schema::create('lms_succession_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('position_id');
            $table->uuid('candidate_employee_id');
            $table->string('readiness_level', 20); // ready_now | ready_1_2_years | ready_3_5_years
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('position_id')->references('id')->on('md_positions')->cascadeOnDelete();
            $table->foreign('candidate_employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_succession_plans');
        Schema::dropIfExists('lms_talent_profiles');
    }
};
