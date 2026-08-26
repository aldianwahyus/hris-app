<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lembur 2 tahap: AtasanLangsung (tier 1) dulu, baru PimpinanKantor
 * (tier 2) — koreksi DEC-92 versi awal. `approver_id`/`decided_at`
 * yang SUDAH ADA tetap berarti "keputusan FINAL" (dibaca apa adanya
 * oleh SPKL PDF) — sekarang diisi keputusan tier 2, bukan lagi
 * Direktur. Kolom baru di sini HANYA jejak tier 1.
 *
 * Status baru 'pending_pimpinan' disisipkan di kolom `status` yang
 * sudah ada (string, bukan enum DB) — tidak perlu migrasi skema
 * tambahan untuk itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ovt_requests', function (Blueprint $table) {
            $table->uuid('atasan_approver_id')->nullable()->after('approver_id');
            $table->timestampTz('atasan_decided_at')->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('ovt_requests', function (Blueprint $table) {
            $table->dropColumn(['atasan_approver_id', 'atasan_decided_at']);
        });
    }
};
