<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kontrol menu Aplikasi Mobile — SYSADMIN/Admin HC (permission
 * sysadmin-content.manage, SAMA dengan kalender libur/pola shift/dst.,
 * lihat 2026_08_28_000001_seed_dynamic_permissions.php) dapat menyala/
 * matikan menu fitur secara BANK-WIDE (satu saklar berlaku untuk SEMUA
 * pengguna mobile — TIDAK per peran, keputusan bisnis eksplisit: aplikasi
 * mobile belum punya penyaringan menu per peran sama sekali hari ini).
 *
 * Tabel REGISTRY kecil, sengaja BUKAN bagian dari cfg_parameters/
 * cfg_parameter_values (itu untuk nilai bisnis numerik ber-efektif-tanggal
 * terikat SK, bukan saklar boolean tampil/sembunyi) — model yang jauh lebih
 * sederhana cocok untuk kebutuhan ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_menu_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 30)->unique(); // cocok dengan kunci route MainStack/MainTab di aplikasi mobile
            $table->string('label', 100);
            $table->boolean('is_enabled')->default(true);
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('updated_by')->references('id')->on('emp_employees')->nullOnDelete();
        });

        // Bawaan SEMUA menyala — memasang fitur ini TIDAK mengubah tampilan
        // aplikasi mobile bagi siapa pun sampai admin secara eksplisit
        // mematikan sesuatu.
        $now = now();
        DB::table('mobile_menu_items')->insert([
            ['id' => (string) Str::uuid(), 'key' => 'absensi', 'label' => 'Absensi', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => (string) Str::uuid(), 'key' => 'cuti', 'label' => 'Cuti', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => (string) Str::uuid(), 'key' => 'lembur', 'label' => 'Lembur', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => (string) Str::uuid(), 'key' => 'sppd', 'label' => 'SPPD', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => (string) Str::uuid(), 'key' => 'izin', 'label' => 'Izin', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => (string) Str::uuid(), 'key' => 'slip_gaji', 'label' => 'Slip Gaji', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => (string) Str::uuid(), 'key' => 'notifikasi', 'label' => 'Notifikasi', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_menu_items');
    }
};
