<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 4 — Absensi (GPS + mesin fingerprint).
 *
 * Dua sumber pencatatan bermuara ke SATU baris per pegawai per hari
 * (att_attendance_records) — pindai pertama hari itu dianggap "masuk",
 * pindai berikutnya "pulang", tidak peduli sumbernya GPS atau
 * fingerprint (pegawai bisa masuk lewat GPS, pulang lewat fingerprint
 * di kantor, atau sebaliknya).
 *
 * att_device_scan_logs adalah tabel singgah (staging) yang berperan
 * sebagai "kotak masuk" dari mesin fingerprint — lihat
 * Domain/AttendanceDeviceGateway untuk alasan protokol perangkat
 * sesungguhnya belum diasumsikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            // Pin/ID pegawai pada mesin fingerprint — BUKAN NRP, karena
            // sebagian besar mesin memakai nomor pendaftaran internalnya
            // sendiri. Pemetaan pin -> pegawai dilakukan lewat kolom ini.
            $table->string('fingerprint_device_pin', 30)->nullable()->unique()->after('email');
        });

        Schema::create('att_attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->date('work_date');

            $table->timestampTz('check_in_at')->nullable();
            $table->string('check_in_source', 20)->nullable();     // gps | fingerprint
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();

            $table->timestampTz('check_out_at')->nullable();
            $table->string('check_out_source', 20)->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();

            // hadir | telat | absen — lihat Domain/AttendanceDayPolicy.
            // "absen" belum pernah ditulis kode mana pun pada Wave ini
            // (menunggu job akhir-hari), disiapkan agar skema tidak perlu
            // migrasi ulang ketika job itu dibangun.
            $table->string('status', 20)->default('hadir');

            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->unique(['employee_id', 'work_date'], 'uq_att_records_emp_date');
        });

        Schema::create('att_device_scan_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_pin', 30);
            $table->timestampTz('scanned_at');
            $table->uuid('employee_id')->nullable();   // null bila pin tidak dikenal saat pemrosesan
            $table->timestampTz('processed_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->index(['processed_at', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_device_scan_logs');
        Schema::dropIfExists('att_attendance_records');

        Schema::table('emp_employees', function (Blueprint $table) {
            $table->dropColumn('fingerprint_device_pin');
        });
    }
};
