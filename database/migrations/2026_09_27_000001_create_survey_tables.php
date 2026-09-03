<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Survei Keterlibatan (eNPS/Pulse) — modul baru (evaluasi PM/client
 * 2026-09-02). Survei + pertanyaan (`svy_surveys`/`svy_questions`),
 * jawaban pegawai (`svy_responses`/`svy_answers` — employee_id di
 * svy_responses NULL bila survei anonim), dan penanda "sudah pernah
 * mengisi" yang TERPISAH dari jawaban itu sendiri
 * (`svy_response_tokens`, UNIQUE per survei+pegawai) — supaya survei
 * anonim tetap bisa mencegah pengisian ganda TANPA membocorkan
 * identitas pengisi lewat tabel jawaban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('svy_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 20)->default('kustom'); // enps | pulse | kustom
            $table->string('scope', 20)->default('bank_wide'); // bank_wide | office
            $table->uuid('office_id')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft'); // draft | aktif | selesai
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('office_id')->references('id')->on('md_offices')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('status');
        });

        Schema::create('svy_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('survey_id');
            $table->text('question_text');
            $table->string('question_type', 20); // nps_0_10 | rating_1_5 | teks | pilihan_ganda
            $table->text('options_json')->nullable();
            $table->integer('display_order')->default(0);

            $table->foreign('survey_id')->references('id')->on('svy_surveys')->restrictOnDelete();
            $table->index('survey_id');
        });

        Schema::create('svy_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('survey_id');
            $table->uuid('employee_id')->nullable();
            $table->timestampTz('submitted_at');

            $table->foreign('survey_id')->references('id')->on('svy_surveys')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('survey_id');
        });

        Schema::create('svy_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('response_id');
            $table->uuid('question_id');
            $table->text('answer_value');

            $table->foreign('response_id')->references('id')->on('svy_responses')->restrictOnDelete();
            $table->foreign('question_id')->references('id')->on('svy_questions')->restrictOnDelete();
            $table->index('question_id');
        });

        Schema::create('svy_response_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('survey_id');
            $table->uuid('employee_id');
            $table->timestampTz('created_at');

            $table->foreign('survey_id')->references('id')->on('svy_surveys')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->unique(['survey_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('svy_response_tokens');
        Schema::dropIfExists('svy_answers');
        Schema::dropIfExists('svy_responses');
        Schema::dropIfExists('svy_questions');
        Schema::dropIfExists('svy_surveys');
    }
};
