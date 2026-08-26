<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assessment Center (BRD §5.4) — online assessment + bank soal +
 * scoring otomatis (multiple_choice) + multi-assessor SEDERHANA (satu
 * assessor per attempt untuk soal esai, bukan konsensus banyak
 * penilai — lihat docblock SubmitAssessmentAttempt/GradeAssessmentAttempt
 * untuk simplifikasi yang disengaja). Bank soal MILIK satu assessment
 * (bukan reusable lintas-assessment) — cukup untuk kebutuhan inti BRD
 * tanpa kerumitan many-to-many.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->decimal('passing_score', 5, 2)->default(70);
            $table->integer('duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('course_id')->references('id')->on('lms_courses')->nullOnDelete();
        });

        Schema::create('lms_assessment_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->integer('sequence');
            $table->text('question_text');
            $table->string('type', 20); // multiple_choice | essay
            $table->json('options')->nullable();
            $table->string('correct_option', 10)->nullable();
            $table->decimal('score_weight', 5, 2)->default(1);
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('assessment_id')->references('id')->on('lms_assessments')->cascadeOnDelete();
            $table->unique(['assessment_id', 'sequence']);
        });

        Schema::create('lms_assessment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->uuid('employee_id');
            $table->string('status', 20)->default('in_progress'); // in_progress | submitted | scored
            $table->timestampTz('started_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->uuid('assessor_id')->nullable();
            $table->timestampTz('scored_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('assessment_id')->references('id')->on('lms_assessments')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index(['assessment_id', 'employee_id']);
        });

        Schema::create('lms_assessment_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->text('answer_text')->nullable();
            $table->decimal('score_awarded', 5, 2)->nullable();
            $table->timestampsTz();

            $table->foreign('attempt_id')->references('id')->on('lms_assessment_attempts')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('lms_assessment_questions')->cascadeOnDelete();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_assessment_answers');
        Schema::dropIfExists('lms_assessment_attempts');
        Schema::dropIfExists('lms_assessment_questions');
        Schema::dropIfExists('lms_assessments');
    }
};
