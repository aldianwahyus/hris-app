<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backup Basis Data Otomatis — Fase 2 (evaluasi PM/client 2026-09-03).
 * Ambang retensi berapa hari cadangan disimpan sebelum dihapus otomatis
 * — murni ambang operasional (bukan dari SK/kebijakan Direksi), pola
 * SAMA CONTRACT_EXPIRY_REMINDER_DAYS: `source_document` SENGAJA null.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parameterId = (string) Str::uuid();

        DB::table('cfg_parameters')->insert([
            'id' => $parameterId,
            'code' => 'DATABASE_BACKUP_RETENTION_DAYS',
            'name' => 'Masa retensi cadangan basis data',
            'value_type' => 'integer',
            'unit' => 'hari',
            'owner_module' => 'System',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        DB::table('cfg_parameter_values')->insert([
            'id' => (string) Str::uuid(),
            'parameter_id' => $parameterId,
            'value' => '30',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'source_document' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    public function down(): void
    {
        $parameterId = DB::table('cfg_parameters')->where('code', 'DATABASE_BACKUP_RETENTION_DAYS')->value('id');

        if ($parameterId !== null) {
            DB::table('cfg_parameter_values')->where('parameter_id', $parameterId)->delete();
            DB::table('cfg_parameters')->where('id', $parameterId)->delete();
        }
    }
};
