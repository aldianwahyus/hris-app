<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 5 jenis data riwayat pegawai — HR-only (hr_admin kantor sendiri,
 * hr_approver/system_admin bank-wide, lihat ResolveEmployeeForHrAction),
 * tulis LANGSUNG (BUKAN maker-checker) — administratif/historis, bukan
 * keputusan bisnis harian, sama kategori dengan OfficeGeofenceController.
 *
 * sanction_type SENGAJA freeform (bukan enum tertutup) — tidak ada
 * daftar baku kategori sanksi yang dikonfirmasi HC.
 * position_description pada riwayat kerja internal SENGAJA teks bebas,
 * TIDAK terikat md_positions — ini catatan historis, bukan penugasan
 * aktif yang perlu FK ke struktur jabatan terkini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_family_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('relationship_type', 20); // 'pasangan' | 'anak'
            $table->string('full_name', 150);
            $table->date('birth_date')->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('emp_internal_work_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('position_description', 200);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('emp_external_work_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('company_name', 150);
            $table->string('position', 150);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('emp_sanctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('sanction_type', 50);
            $table->date('sanction_date');
            $table->text('reason');
            $table->string('reference_document', 150)->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('emp_health_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->date('record_date');
            $table->text('note');
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_health_records');
        Schema::dropIfExists('emp_sanctions');
        Schema::dropIfExists('emp_external_work_histories');
        Schema::dropIfExists('emp_internal_work_histories');
        Schema::dropIfExists('emp_family_members');
    }
};
