<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat kantor — sebelumnya md_offices HANYA punya lat/long
 * (geofence), tidak ada alamat teks sama sekali. Dibutuhkan supaya
 * header dokumen cetak (Memo Internal/Nota Debet/Jurnal Slip
 * pembayaran lembur & bekal cuti) bisa menampilkan alamat kantor yang
 * SESUNGGUHNYA menerbitkan dokumen itu, bukan alamat kantor pusat
 * yang di-hardcode untuk SEMUA kantor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('md_offices', function (Blueprint $table) {
            $table->text('address')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('md_offices', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
