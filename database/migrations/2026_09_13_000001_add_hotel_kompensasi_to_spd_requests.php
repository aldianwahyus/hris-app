<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kompensasi Hotel — kolom BARU untuk SPPD Massal saja (self-service
 * SubmitSppdRequest/SppdRequestController TIDAK menawarkan komponen
 * ini, tidak disentuh). Tarif `hotel_compensation` sudah lama disemai
 * di pay_sppd_tariffs (2026_01_09_000002_seed_sppd_tariffs.php) TAPI
 * TIDAK PERNAH dipakai di mana pun sampai sekarang — SppdBudgetCalculator
 * sekarang menghitungnya (lihat SppdBudgetResult::$hotelKompensasi),
 * SubmitSppdMemoGroup menyimpannya kalau dicentang Admin HC/Cabang
 * (pegawai TIDAK mengambil fasilitas hotel yang disediakan Bank).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->bigInteger('hotel_kompensasi_cents')->nullable()->after('estimasi_hotel_cents');
        });
    }

    public function down(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->dropColumn('hotel_kompensasi_cents');
        });
    }
};
