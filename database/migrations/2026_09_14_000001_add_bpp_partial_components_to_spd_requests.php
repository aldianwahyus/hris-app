<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen TERPISAH untuk skenario sebagian-hari BPP — SENGAJA bukan
 * sekadar "preset" yang menimpa persen/hari uang_makan_cents/
 * uang_saku_cents utuh (percobaan awal yang KELIRU: menerapkan 25%/70%/
 * 30% ke SELURUH total_days trip, padahal BPP menyebutnya berlaku HANYA
 * untuk hari/kondisi tertentu — H-1/H+1 hanya 1-2 hari transit dari trip
 * yang bisa jadi berhari-hari, bukan keseluruhan trip). Dengan kolom
 * TERPISAH, admin bisa mengisi hari normal (mis. 2 hari @100% di
 * uang_makan_cents biasa) DAN hari transit (1 hari @25% di kolom ini)
 * SECARA BERSAMAAN — dijumlahkan, bukan saling menimpa.
 *
 * - uang_makan_h1_cents / uang_saku_h1_cents — BPP §III.B.3: hari
 *   transit H-1/H+1, uang makan DAN uang saku sebesar 25% dari tarif.
 * - uang_makan_konsumsi_cents — BPP §III.B.4: uang makan ditanggung
 *   sebagian oleh panitia acara/diklat (70% jika 1x makan ditanggung,
 *   30% jika 2x makan ditanggung — persen diisi bebas oleh admin sesuai
 *   kondisi, TIDAK ada tarif tersendiri, memakai tarif Uang Makan yang
 *   sama). TIDAK ADA aturan setara untuk Uang Saku di BPP ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->bigInteger('uang_makan_h1_cents')->nullable()->after('uang_makan_cents');
            $table->bigInteger('uang_saku_h1_cents')->nullable()->after('uang_saku_cents');
            $table->bigInteger('uang_makan_konsumsi_cents')->nullable()->after('uang_makan_h1_cents');
        });
    }

    public function down(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->dropColumn(['uang_makan_h1_cents', 'uang_saku_h1_cents', 'uang_makan_konsumsi_cents']);
        });
    }
};
