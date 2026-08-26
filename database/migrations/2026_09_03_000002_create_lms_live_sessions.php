<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live Learning & Mentoring (BRD §5.10) — app ini TIDAK punya
 * infrastruktur streaming/hosting video sendiri. "Webinar/live
 * training" = penjadwalan + tautan rapat eksternal (meeting_url,
 * pola sama Digital Library external_url), "recording session" =
 * tautan rekaman eksternal opsional, BUKAN perekaman sungguhan.
 * Mentoring DIGABUNG ke model yang sama (session_type +
 * facilitator_employee_id sebagai mentor/pelatih) — bukan subsistem
 * pasangan mentor-mentee terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_live_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('session_type', 20); // webinar | coaching | mentoring
            $table->uuid('course_id')->nullable();
            $table->uuid('facilitator_employee_id')->nullable();
            $table->timestampTz('scheduled_at');
            $table->string('meeting_url')->nullable();
            $table->string('recording_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('course_id')->references('id')->on('lms_courses')->nullOnDelete();
            $table->foreign('facilitator_employee_id')->references('id')->on('emp_employees')->nullOnDelete();
        });

        Schema::create('lms_live_session_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->uuid('employee_id');
            $table->string('status', 20)->default('registered'); // registered | attended
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('session_id')->references('id')->on('lms_live_sessions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->unique(['session_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_live_session_participants');
        Schema::dropIfExists('lms_live_sessions');
    }
};
