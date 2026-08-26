<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Izin Tidak Masuk Bekerja — TERPISAH dari Cuti (leave_balances TIDAK
 * disentuh sama sekali): sakit tanpa surat dokter, keperluan keluarga,
 * atau alasan lain yang tidak menghabiskan jatah cuti tahunan. Alur
 * SATU TAHAP (Atasan Langsung, OFFICE_TREE) — pola sama Tukar Shift/
 * Absen Luar Kantor, BUKAN 2 tahap seperti Cuti/Lembur/SPPD, karena
 * tidak berdampak finansial/saldo cuti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 40)->unique();
            $table->uuid('employee_id');
            $table->string('category', 30); // sakit | keperluan_keluarga | lainnya
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1);
            $table->text('reason');

            // Wajib diisi untuk category=sakit (ditegakkan di
            // SubmitIzinRequestForm + SubmitIzinRequest), opsional untuk
            // kategori lain — disimpan di disk 's3' (MinIO), pola sama
            // dokumen SK (DecisionLetterController).
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();

            $table->string('status', 20)->default('pending');
            $table->uuid('approver_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->uuid('wf_instance_id')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->index(['employee_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_requests');
    }
};
