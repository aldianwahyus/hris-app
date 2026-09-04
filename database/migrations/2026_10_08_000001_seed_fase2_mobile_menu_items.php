<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aplikasi Mobile (Fase 2) — 4 menu baru (Aset Saya, Ajukan Dokumen,
 * Tiket Bantuan, Survei), pola SAMA PERSIS
 * 2026_09_15_000001_create_mobile_menu_items.php. Bawaan SEMUA
 * menyala — MobileMenuSettingsController/MobileMenuApiController yang
 * SUDAH ADA generik atas isi tabel ini, TIDAK perlu diubah.
 */
return new class extends Migration
{
    private const ITEMS = [
        ['key' => 'aset', 'label' => 'Aset Saya'],
        ['key' => 'dokumen', 'label' => 'Ajukan Dokumen'],
        ['key' => 'helpdesk', 'label' => 'Tiket Bantuan'],
        ['key' => 'survei', 'label' => 'Survei'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ITEMS as $item) {
            DB::table('mobile_menu_items')->insert([
                'id' => (string) Str::uuid(),
                'key' => $item['key'],
                'label' => $item['label'],
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('mobile_menu_items')->whereIn('key', array_column(self::ITEMS, 'key'))->delete();
    }
};
