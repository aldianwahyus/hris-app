<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Social & Collaborative Learning (BRD §5.9) — forum diskusi, dasar
 * "knowledge sharing"/"community learning" (satu model data yang sama
 * cukup memenuhi ketiganya, lihat plan). Thread BOLEH umum (course_id
 * nullable) — tidak semua diskusi terikat satu kursus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_forum_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->nullable();
            $table->uuid('employee_id');
            $table->string('title', 200);
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('course_id')->references('id')->on('lms_courses')->nullOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
        });

        Schema::create('lms_forum_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('thread_id');
            $table->uuid('employee_id');
            $table->text('body');
            $table->timestampsTz();

            $table->foreign('thread_id')->references('id')->on('lms_forum_threads')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_forum_replies');
        Schema::dropIfExists('lms_forum_threads');
    }
};
