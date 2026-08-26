<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPPD SEKARANG 2 TAHAP SERAGAM untuk SEMUA trip_category (koreksi atas
 * pemilahan lama per kategori — lihat SppdApprovalController): Atasan
 * Langsung dulu (status 'pending', OFFICE_TREE), baru Pimpinan Kantor
 * (status 'pending_pimpinan', OFFICE persis). Pola PERSIS
 * 2026_08_20_000004_add_two_tier_leave_approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->uuid('atasan_approver_id')->nullable()->after('approver_id');
            $table->timestampTz('atasan_decided_at')->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->dropColumn(['atasan_approver_id', 'atasan_decided_at']);
        });
    }
};
