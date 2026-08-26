<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuti jadi 2 TAHAP (Atasan Langsung → Pimpinan Kantor), pola PERSIS
 * 2026_08_19_000002_add_two_tier_overtime_approval.php — hr_approver
 * DIHAPUS dari jalur KEPUTUSAN Cuti (tetap dipakai untuk hal lain:
 * checker data pegawai, payroll, pencairan bank-wide).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->uuid('atasan_approver_id')->nullable()->after('approver_id');
            $table->timestampTz('atasan_decided_at')->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['atasan_approver_id', 'atasan_decided_at']);
        });
    }
};
