<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ambang pengingat kontrak akan berakhir — BUKAN dari SK resmi (beda
 * dari parameter lain di 2026_01_01_000003_seed_configuration_values.php)
 * karena ini murni ambang operasional/UX (kapan HC mulai diingatkan),
 * bukan angka yang diregulasi kebijakan Direksi. `source_document`
 * SENGAJA null, jujur terhadap keterbatasan ini (pola sama catatan
 * PAYROLL_CUTOFF_DAY di migrasi konfigurasi awal).
 */
return new class extends Migration
{
    public function up(): void
    {
        $parameterId = (string) Str::uuid();

        DB::table('cfg_parameters')->insert([
            'id' => $parameterId,
            'code' => 'CONTRACT_EXPIRY_REMINDER_DAYS',
            'name' => 'Ambang pengingat kontrak akan berakhir',
            'value_type' => 'integer',
            'unit' => 'hari',
            'owner_module' => 'Employee',
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
        $parameterId = DB::table('cfg_parameters')->where('code', 'CONTRACT_EXPIRY_REMINDER_DAYS')->value('id');

        if ($parameterId !== null) {
            DB::table('cfg_parameter_values')->where('parameter_id', $parameterId)->delete();
            DB::table('cfg_parameters')->where('id', $parameterId)->delete();
        }
    }
};
