<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran + persetujuan + penyelesaian pelatihan dalam SATU tabel
 * flat — pola sama `shf_swap_requests`/`att_outside_attendance_requests`
 * (satu entitas dengan siklus hidup pendek, hindari join tak perlu).
 *
 * `completion_status`/`score`/`certificate_number` nullable karena baru
 * terisi setelah HC mencatat kelulusan (RecordCourseCompletion) —
 * SETELAH status disetujui, bukan saat mendaftar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('enrollment_number', 30)->unique();
            $table->uuid('batch_id');
            $table->uuid('employee_id');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('requested_at');
            $table->uuid('approver_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->string('completion_status', 20)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('certificate_number', 40)->nullable()->unique();
            $table->uuid('recorded_by')->nullable();
            $table->uuid('wf_instance_id')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('batch_id')->references('id')->on('lms_course_batches')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('batch_id');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_enrollments');
    }
};
