<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi 2 tahap persetujuan Lembur yang SUDAH ADA — status
 * 'approved' sekarang jadi titik masuk tahap PEMBAYARAN, bukan akhir
 * alur. Pola PERSIS 2026_01_10_000001_add_sppd_disbursement.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ovt_requests', function (Blueprint $table) {
            $table->uuid('disbursed_by')->nullable()->after('decided_at');
            $table->timestampTz('disbursed_at')->nullable()->after('disbursed_by');
            $table->string('disbursement_reference', 100)->nullable()->after('disbursed_at');
            // Tidak menambah indeks 'status' terpisah — sudah ada indeks
            // komposit (status, approval_deadline) sejak skema awal yang
            // mencakup query filter status saja (leftmost prefix).
        });
    }

    public function down(): void
    {
        Schema::table('ovt_requests', function (Blueprint $table) {
            $table->dropColumn(['disbursed_by', 'disbursed_at', 'disbursement_reference']);
        });
    }
};
