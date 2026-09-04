<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pengaturan Perusahaan (Fase 2) — identitas perusahaan (nama+lambang)
 * yang tampil di dokumen resmi cetak (Memo Internal, Nota Debet,
 * Jurnal Slip, dst., lihat App\Interfaces\Http\Support\CompanyProfile).
 * SATU baris SAJA (singleton, pola diperkuat lewat lock di
 * CompanySettingsController, BUKAN constraint basis data — mengikuti
 * pola tabel registry kecil seperti mobile_menu_items, bukan
 * cfg_parameters yang untuk nilai ber-efektif-tanggal terikat SK).
 *
 * `logo_path` NULLABLE = pakai lambang bawaan aplikasi (berkas di
 * public/images/) — admin BELUM WAJIB mengunggah apa pun supaya
 * pemasangan fitur ini tidak mengubah tampilan dokumen bagi siapa pun
 * sampai diubah eksplisit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name', 200);
            $table->string('logo_path')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('updated_by')->references('id')->on('emp_employees')->nullOnDelete();
        });

        $now = now();

        DB::table('company_settings')->insert([
            'id' => (string) Str::uuid(),
            'company_name' => 'PT Bank NTB Syariah',
            'logo_path' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
