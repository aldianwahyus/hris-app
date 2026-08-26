<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gamifikasi (BRD §5.8) — point system, badge, leaderboard, challenge.
 * `lms_gamification_points` SENGAJA ledger append-only (pola sama
 * aud_change_logs — histori peristiwa, bukan kolom total yang ditimpa)
 * — total poin seseorang = SUM baris miliknya, bukan kolom tersendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_gamification_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->integer('points');
            $table->string('reason', 200);
            $table->string('source_type', 50)->nullable();
            $table->uuid('source_id')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('lms_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('icon', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->integer('version')->default(1);
        });

        Schema::create('lms_employee_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('badge_id');
            $table->timestampTz('awarded_at');
            $table->uuid('awarded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->foreign('badge_id')->references('id')->on('lms_badges')->cascadeOnDelete();
            $table->unique(['employee_id', 'badge_id']);
        });

        Schema::create('lms_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('points_reward')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);
        });

        Schema::create('lms_challenge_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('challenge_id');
            $table->uuid('employee_id');
            $table->string('status', 20)->default('joined'); // joined | completed
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('challenge_id')->references('id')->on('lms_challenges')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->unique(['challenge_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_challenge_participants');
        Schema::dropIfExists('lms_challenges');
        Schema::dropIfExists('lms_employee_badges');
        Schema::dropIfExists('lms_badges');
        Schema::dropIfExists('lms_gamification_points');
    }
};
