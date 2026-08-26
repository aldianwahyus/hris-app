<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pencairan SPPD — tahap lanjutan setelah persetujuan (lihat catatan
 * "realisasi... belum dibangun" pada 2026_01_09_000001). status
 * mendapat nilai terminal baru 'disbursed', mengikuti pengganti
 * pending -> approved/rejected -> disbursed (satu kolom status
 * menjalankan seluruh mesin status, konsisten dengan pay_payroll_runs).
 *
 * disbursement_reference SENGAJA teks bebas (bukan nomor terbit
 * sistem) — nomor referensi transfer berasal dari Core Banking/
 * Treasury eksternal, HRIS hanya mencatatnya (bukan sumber kebenaran
 * transaksi keuangan itu sendiri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->uuid('disbursed_by')->nullable()->after('decided_at');
            $table->timestampTz('disbursed_at')->nullable()->after('disbursed_by');
            $table->string('disbursement_reference', 100)->nullable()->after('disbursed_at');

            $table->index(['status'], 'idx_spd_requests_status');
        });
    }

    public function down(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->dropIndex('idx_spd_requests_status');
            $table->dropColumn(['disbursed_by', 'disbursed_at', 'disbursement_reference']);
        });
    }
};
