<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekrutmen (ATS) — modul baru (evaluasi PM/client 2026-09-02),
 * TERBESAR dari 9 modul. Maker-checker requisition (pola PERSIS
 * emp_new_employee_requests) → posting dibuka → PUBLIK tanpa login
 * melamar (rec_candidates/rec_applications) → HC kelola pipeline →
 * tawaran (rec_job_offers, response_token dipakai URL publik) →
 * diterima → bridging ke SubmitNewEmployeeRequest yang SUDAH ADA
 * (lihat JobOfferController::convertToEmployee()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rec_job_requisitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('office_id');
            $table->uuid('position_id');
            $table->integer('requested_headcount');
            $table->text('justification');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->uuid('requested_by');
            $table->uuid('approver_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('office_id')->references('id')->on('md_offices')->restrictOnDelete();
            $table->foreign('position_id')->references('id')->on('md_positions')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('approver_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('status');
        });

        Schema::create('rec_job_postings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requisition_id');
            $table->string('title', 200);
            $table->text('description');
            $table->text('requirements');
            $table->string('employment_status_offered', 20); // tetap | trainee | kontrak | outsource
            $table->boolean('is_open')->default(false);
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('requisition_id')->references('id')->on('rec_job_requisitions')->restrictOnDelete();
            $table->index('is_open');
        });

        Schema::create('rec_candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 150);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('resume_path', 255)->nullable();
            $table->string('source', 50)->default('lowongan_publik');
            $table->timestampTz('created_at');

            $table->unique('email');
        });

        Schema::create('rec_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('posting_id');
            $table->uuid('candidate_id');
            $table->string('status', 20)->default('melamar'); // melamar | seleksi_berkas | wawancara | penawaran | diterima | ditolak
            $table->text('stage_notes')->nullable();
            $table->timestampTz('applied_at');
            $table->timestampTz('updated_at');
            $table->integer('version')->default(1);

            $table->foreign('posting_id')->references('id')->on('rec_job_postings')->restrictOnDelete();
            $table->foreign('candidate_id')->references('id')->on('rec_candidates')->restrictOnDelete();
            $table->unique(['posting_id', 'candidate_id']);
            $table->index('status');
        });

        Schema::create('rec_interview_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');
            $table->timestampTz('scheduled_at');
            $table->string('location_or_link', 255);
            $table->uuid('interviewer_employee_id')->nullable();
            $table->string('status', 20)->default('dijadwalkan'); // dijadwalkan | selesai | dibatalkan
            $table->text('feedback')->nullable();
            $table->integer('rating')->nullable(); // 1-5
            $table->timestampsTz();

            $table->foreign('application_id')->references('id')->on('rec_applications')->restrictOnDelete();
            $table->foreign('interviewer_employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('application_id');
        });

        Schema::create('rec_job_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');
            $table->uuid('proposed_position_id');
            $table->uuid('proposed_office_id');
            $table->text('proposed_salary_notes')->nullable();
            $table->string('response_token', 64)->unique();
            $table->timestampTz('offered_at');
            $table->string('status', 20)->default('menunggu'); // menunggu | diterima | ditolak
            $table->timestampTz('responded_at')->nullable();

            $table->foreign('application_id')->references('id')->on('rec_applications')->restrictOnDelete();
            $table->foreign('proposed_position_id')->references('id')->on('md_positions')->restrictOnDelete();
            $table->foreign('proposed_office_id')->references('id')->on('md_offices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rec_job_offers');
        Schema::dropIfExists('rec_interview_schedules');
        Schema::dropIfExists('rec_applications');
        Schema::dropIfExists('rec_candidates');
        Schema::dropIfExists('rec_job_postings');
        Schema::dropIfExists('rec_job_requisitions');
    }
};
