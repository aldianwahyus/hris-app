<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usulan PEGAWAI BARU (SYSADMIN, maker) — pola PERSIS
 * emp_profile_change_requests, tapi untuk PENAMBAHAN bukan REVISI:
 * tidak ada employee_id (belum ada baris emp_employees-nya), seluruh
 * data calon pegawai disimpan di proposed_data sampai hr_approver
 * (checker) menyetujui — baru saat itu baris emp_employees + users
 * benar-benar dibuat (lihat DecideNewEmployeeRequest). §6.3 tetap
 * ditegakkan untuk PENAMBAHAN, bukan cuma REVISI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_new_employee_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requested_by');

            $table->json('proposed_data'); // nrp, full_name, birth_date, gender, office_id, position_id, employment_status, person_grade, job_grade, salary_step

            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->uuid('created_employee_id')->nullable(); // diisi saat approved — jejak baris emp_employees yang dihasilkan

            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('requested_by')->references('id')->on('emp_employees');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_new_employee_requests');
    }
};
