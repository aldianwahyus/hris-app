<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog kursus + jadwal kelas (batch) — modul LMS. Pola sama
 * `shf_shift_patterns` (definisi/referensi, dikelola HC, soft-delete
 * dengan `deleted_at`, bukan proses bisnis per pegawai jadi tulis
 * langsung tanpa maker-checker).
 *
 * `lms_course_batches.location` SENGAJA teks bebas, BUKAN office_id —
 * venue pelatihan tidak selalu kantor sendiri (bisa hotel, gedung
 * pelatihan eksternal, dsb).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->timestampTz('deleted_at')->nullable();
            $table->integer('version')->default(1);
        });

        Schema::create('lms_course_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('batch_code', 30);
            $table->string('location', 200)->nullable();
            $table->string('instructor_name', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('registration_deadline')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('course_id')->references('id')->on('lms_courses')->cascadeOnDelete();
            $table->index('course_id');
            $table->unique(['course_id', 'batch_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_batches');
        Schema::dropIfExists('lms_courses');
    }
};
