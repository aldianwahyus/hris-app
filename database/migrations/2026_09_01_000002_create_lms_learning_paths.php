<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learning Path & Career Development (BRD §5.2) — jalur pembelajaran
 * terstruktur per jabatan. "Integrasi dengan IDP" DIINTERPRETASIKAN
 * sebagai: learning path milik jabatan pegawai ITU SENDIRI yang jadi
 * rencana pengembangannya (lihat ComputeLearningPathProgress) — TIDAK
 * ada tabel IDP terpisah, realisasinya dibaca dari lms_enrollments
 * yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_learning_paths', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('position_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('position_id')->references('id')->on('md_positions')->cascadeOnDelete();
        });

        Schema::create('lms_learning_path_courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learning_path_id');
            $table->uuid('course_id');
            $table->integer('sequence');
            $table->boolean('is_mandatory')->default(true);
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('learning_path_id')->references('id')->on('lms_learning_paths')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('lms_courses')->cascadeOnDelete();
            $table->unique(['learning_path_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_learning_path_courses');
        Schema::dropIfExists('lms_learning_paths');
    }
};
