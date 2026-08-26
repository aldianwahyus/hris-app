<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan absen luar kantor — pegawai lapangan mengajukan, disetujui
 * Pimpinan Kantor (SATU TAHAP, lingkup kantor sendiri — role
 * `pimpinan_kantor` yang sama dipakai untuk Pimpinan Unit/Divisi
 * di kantor pusat maupun Kepala KC/KCP di cabang, lihat
 * OutsideAttendanceApprovalController). Unique (employee_id, work_date)
 * mencegah pengajuan ganda untuk hari yang sama, sekaligus jadi jaring
 * pengaman saat DecideOutsideAttendanceRequest meng-upsert
 * att_attendance_records pada persetujuan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_outside_attendance_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 40)->unique();
            $table->uuid('employee_id');
            $table->date('work_date');
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->uuid('approver_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->uuid('wf_instance_id')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->foreign('approver_id')->references('id')->on('emp_employees');
            $table->unique(['employee_id', 'work_date'], 'uq_att_outside_req_emp_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_outside_attendance_requests');
    }
};
