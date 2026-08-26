<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maker-checker perubahan data induk pegawai (Data Pegawai, TOR Fase I).
 *
 * hr_admin (maker, OFFICE) mengajukan perubahan atas field yang
 * di-whitelist (lihat App\Modules\Employee\Domain\EditableEmployeeField)
 * — TIDAK langsung menulis emp_employees. hr_approver (checker,
 * BANK_WIDE) menyetujui/menolak; hanya PERSETUJUAN yang menerapkan
 * proposed_changes ke emp_employees.
 *
 * Tidak memakai Shared/Workflow (wf_instances) — mengikuti pola SPPD
 * (App\Modules\Sppd\Application\SubmitSppdRequest), bukan pola Cuti/
 * Lembur: satu checker tunggal tanpa matriks kewenangan berjenjang
 * tidak butuh mesin alur penuh, cukup status pending/approved/rejected
 * yang ditegakkan langsung di controller (konsisten dengan SPPD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_profile_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('requested_by');

            // Hanya field yang di-whitelist EditableEmployeeField —
            // ditegakkan di Application layer, bukan di skema.
            $table->json('proposed_changes');
            $table->json('previous_values'); // snapshot SAAT diajukan — dasar tampilan diff & deteksi basi

            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->foreign('requested_by')->references('id')->on('emp_employees');
            $table->index(['status', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_profile_change_requests');
    }
};
