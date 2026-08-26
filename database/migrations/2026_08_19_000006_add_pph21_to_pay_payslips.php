<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPh 21 dihitung SEMENTARA (provisional) mulai sekarang, dari basis
 * Gaji Kotor yang BELUM lengkap (Tunjangan Kinerja/Kemahalan belum
 * ada) — keputusan eksplisit pengguna, ditandai jelas di slip
 * (pending_components), BUKAN diam-diam dianggap final. Nullable:
 * pegawai tanpa data PTKP (marital_status) lengkap tetap dapat
 * digenerate slipnya, hanya PPh21-nya yang tertunda per pegawai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_payslips', function (Blueprint $table) {
            $table->string('pph21_golongan', 1)->nullable()->after('take_home_partial_cents');
            $table->bigInteger('pph21_cents')->nullable()->after('pph21_golongan');
        });
    }

    public function down(): void
    {
        Schema::table('pay_payslips', function (Blueprint $table) {
            $table->dropColumn(['pph21_golongan', 'pph21_cents']);
        });
    }
};
