<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding — modul baru (evaluasi PM/client 2026-09-02).
 * Maker-checker (`off_separation_requests`) pola PERSIS
 * emp_new_employee_requests. Disetujui → GenerateClearanceChecklist
 * membangkitkan `off_clearance_items` (item standar + SATU item per
 * `ast_assignments` aktif milik pegawai itu, lihat Modul 1 Manajemen
 * Aset). Seluruh item selesai → MarkSeparationComplete mengisi
 * `emp_employees.separated_at`. `off_exit_interviews` opsional,
 * SATU per separation (UNIQUE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('off_separation_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('separation_type', 20); // resign | phk | pensiun | meninggal | kontrak_berakhir
            $table->text('reason');
            $table->date('requested_last_date');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | selesai
            $table->uuid('requested_by');
            $table->uuid('approver_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('approver_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('status');
        });

        Schema::create('off_clearance_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('separation_id');
            $table->string('item_name', 200);
            $table->string('category', 20); // aset | it | keuangan | hc | lainnya
            $table->boolean('is_done')->default(false);
            $table->uuid('done_by')->nullable();
            $table->timestampTz('done_at')->nullable();

            $table->foreign('separation_id')->references('id')->on('off_separation_requests')->restrictOnDelete();
            $table->foreign('done_by')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('separation_id');
        });

        Schema::create('off_exit_interviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('separation_id');
            $table->uuid('employee_id');
            $table->text('reason_detail')->nullable();
            $table->integer('satisfaction_rating')->nullable(); // 1-5
            $table->boolean('would_recommend')->nullable();
            $table->text('comments')->nullable();
            $table->timestampTz('submitted_at')->nullable();

            $table->foreign('separation_id')->references('id')->on('off_separation_requests')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->unique('separation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('off_exit_interviews');
        Schema::dropIfExists('off_clearance_items');
        Schema::dropIfExists('off_separation_requests');
    }
};
