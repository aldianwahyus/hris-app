<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penjadwalan shift — pola shift (definisi jam kerja bergilir),
 * penugasan shift per pegawai (effective-dated, pola sama
 * cfg_parameter_values), dan pengajuan tukar shift (1 tahap,
 * Atasan Langsung, lihat ShiftSwapApprovalController).
 *
 * SENGAJA TIDAK menyambungkan ke AttendanceDayPolicy pada fase ini —
 * deteksi telat Absensi TETAP pakai jam kerja global bank-wide
 * (ATT_WORK_START_TIME/ATT_WORK_END_TIME). Menjadikan Absensi
 * shift-aware adalah utang teknis terpisah untuk fase mendatang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shf_shift_patterns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            // Shift malam yang lintas hari (mis. 23:00-07:00) — flag
            // eksplisit, jangan andalkan perbandingan start_time > end_time
            // di kode konsumen.
            $table->boolean('crosses_midnight')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->uuid('created_by')->nullable();
            $table->integer('version')->default(1);
        });

        Schema::create('shf_employee_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('shift_pattern_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->foreign('shift_pattern_id')->references('id')->on('shf_shift_patterns');
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('shf_swap_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 40)->unique();
            $table->uuid('requesting_employee_id');
            $table->uuid('counterpart_employee_id');
            $table->date('swap_date');
            $table->uuid('requesting_original_pattern_id');
            $table->uuid('counterpart_original_pattern_id');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('approver_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->uuid('wf_instance_id')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('requesting_employee_id')->references('id')->on('emp_employees');
            $table->foreign('counterpart_employee_id')->references('id')->on('emp_employees');
            $table->foreign('requesting_original_pattern_id')->references('id')->on('shf_shift_patterns');
            $table->foreign('counterpart_original_pattern_id')->references('id')->on('shf_shift_patterns');
            $table->index(['requesting_employee_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shf_swap_requests');
        Schema::dropIfExists('shf_employee_assignments');
        Schema::dropIfExists('shf_shift_patterns');
    }
};
