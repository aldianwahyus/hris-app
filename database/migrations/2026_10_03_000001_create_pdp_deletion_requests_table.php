<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perkakas Data Pribadi (UU PDP) — Fase 2 (evaluasi PM/client
 * 2026-09-03). Permintaan penghapusan data DITINJAU MANUAL oleh
 * hr_approver, TIDAK PERNAH otomatis — data kepegawaian punya
 * kewajiban retensi hukum lain (pajak, ketenagakerjaan) yang tidak
 * boleh dilanggar oleh penghapusan sepihak. Status
 * pending → reviewed (hr_approver akan memproses) ATAU rejected
 * (ditolak dengan alasan) → completed (penanganan data sungguhan
 * telah dituntaskan, ditandai manual setelah 'reviewed').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdp_deletion_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending | reviewed | rejected | completed
            $table->uuid('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdp_deletion_requests');
    }
};
