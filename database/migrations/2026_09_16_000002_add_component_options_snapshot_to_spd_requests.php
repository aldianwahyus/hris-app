<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot persen+hari yang BENAR dipakai per komponen lumpsum saat
 * SubmitSppdMemoGroup::handle() menyimpan baris ini — hanya angka
 * cents akhir yang tersimpan sebelumnya (percent/days dipakai
 * SubmitSppdMemoGroup::computeCents() cuma sekali di memori lalu
 * dibuang), sehingga formulir cetak resmi (Rincian Lumpsum) yang
 * menampilkan baris "Rp [tarif pada persentase itu] X [hari] Hari"
 * per tingkatan (100%/75%/70%/50%/30%/25%) TIDAK BISA direkonstruksi
 * dari cents akhir saja — banyak kombinasi persen×hari menghasilkan
 * total yang sama.
 *
 * Nullable — baris SEBELUM migrasi ini tidak punya snapshot (cetak
 * Rincian Lumpsum untuk baris lama menampilkan Rp0 di semua baris
 * persentase, bukan error).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->jsonb('component_options_snapshot')->nullable()->after('memo_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->dropColumn('component_options_snapshot');
        });
    }
};
