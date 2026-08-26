<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital Library (BRD §5.7) — repository konten pelatihan. Materi
 * BOLEH berdiri sendiri (course_id nullable) — tidak semua materi
 * terikat satu kursus formal. `file_path` (unggahan S3, pola sama SK)
 * ATAU `external_url` (tautan video/eksternal — TIDAK hosting video
 * sendiri, di luar scope infrastruktur) — validasi salah-satu-wajib
 * dicek di controller, bukan constraint DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_library_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('external_url')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('course_id')->references('id')->on('lms_courses')->nullOnDelete();
            $table->index('category');
        });

        // Log tulis-sekali (tracking aktivitas §5.7) — TANPA updated_at/
        // version, pola sama att_device_scan_logs.
        Schema::create('lms_library_access_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('library_item_id');
            $table->uuid('employee_id');
            $table->timestampTz('accessed_at');
            $table->timestampTz('created_at');

            $table->foreign('library_item_id')->references('id')->on('lms_library_items')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('library_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_library_access_logs');
        Schema::dropIfExists('lms_library_items');
    }
};
