<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi per SESI (hari pertemuan) — bukan satu flag "hadir/tidak"
 * untuk seluruh batch. Training multi-hari (mis. 3 hari) butuh absensi
 * per hari, sejalan BRD §5.3 "Absensi" sebagai fungsi terpisah dari
 * Sertifikat/Evaluasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_course_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->integer('sequence');
            $table->date('session_date');
            $table->string('topic', 200)->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('batch_id')->references('id')->on('lms_course_batches')->cascadeOnDelete();
            $table->unique(['batch_id', 'sequence']);
        });

        Schema::create('lms_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->uuid('enrollment_id');
            $table->string('status', 10);
            $table->uuid('recorded_by')->nullable();
            $table->timestampTz('recorded_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('session_id')->references('id')->on('lms_course_sessions')->cascadeOnDelete();
            $table->foreign('enrollment_id')->references('id')->on('lms_enrollments')->cascadeOnDelete();
            $table->unique(['session_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_attendances');
        Schema::dropIfExists('lms_course_sessions');
    }
};
