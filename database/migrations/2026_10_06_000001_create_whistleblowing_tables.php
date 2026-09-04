<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whistleblowing/Pengaduan (Fase 2) — modul BARU, SENGAJA terpisah
 * dari Helpdesk (model kerahasiaan berbeda total). `reporter_employee_id`
 * NULLABLE dan SENGAJA dikosongkan (bukan disembunyikan di UI saja)
 * saat `is_anonymous`, lihat App\Modules\Whistleblowing\Application\
 * SubmitReport — TIDAK ADA tabel token anti-duplikat seperti Survei,
 * karena token identitas untuk laporan anonim SAMA SAJA membocorkan
 * pelapor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wb_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category', 30); // fraud | harassment | corruption | code_of_conduct | other
            $table->text('description');
            $table->boolean('is_anonymous')->default(false);
            $table->uuid('reporter_employee_id')->nullable();
            $table->string('status', 20)->default('baru'); // baru | diproses | selesai
            $table->uuid('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('reporter_employee_id')->references('id')->on('emp_employees')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wb_reports');
    }
};
