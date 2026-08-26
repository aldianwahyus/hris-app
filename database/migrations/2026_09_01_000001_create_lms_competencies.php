<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competency-Based Learning (BRD §5.1) — peta kompetensi per jabatan
 * (required_level), level kompetensi individu (current_level), dan
 * penanda kursus mana menutup kompetensi apa (dasar rekomendasi
 * berbasis aturan, lihat RecommendCoursesForGap — BUKAN AI/ML,
 * §5.13 AI-Based Recommendation tetap di luar scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_competencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->integer('version')->default(1);
        });

        Schema::create('lms_position_competencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('position_id');
            $table->uuid('competency_id');
            $table->unsignedTinyInteger('required_level');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('position_id')->references('id')->on('md_positions')->cascadeOnDelete();
            $table->foreign('competency_id')->references('id')->on('lms_competencies')->cascadeOnDelete();
            $table->unique(['position_id', 'competency_id']);
        });

        Schema::create('lms_employee_competencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('competency_id');
            $table->unsignedTinyInteger('current_level');
            $table->uuid('assessed_by')->nullable();
            $table->timestampTz('assessed_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->foreign('competency_id')->references('id')->on('lms_competencies')->cascadeOnDelete();
            $table->unique(['employee_id', 'competency_id']);
        });

        Schema::create('lms_course_competencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('competency_id');
            $table->timestampTz('created_at');

            $table->foreign('course_id')->references('id')->on('lms_courses')->cascadeOnDelete();
            $table->foreign('competency_id')->references('id')->on('lms_competencies')->cascadeOnDelete();
            $table->unique(['course_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_competencies');
        Schema::dropIfExists('lms_employee_competencies');
        Schema::dropIfExists('lms_position_competencies');
        Schema::dropIfExists('lms_competencies');
    }
};
