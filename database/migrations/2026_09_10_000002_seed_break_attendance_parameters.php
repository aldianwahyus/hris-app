<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jam Istirahat/Kembali — pola SAMA PERSIS ATT_WORK_START_TIME/
 * ATT_LATE_GRACE_MINUTES (2026_01_06_000002_seed_attendance_parameters.php):
 * PARAMETER berversi, bukan konstanta, supaya admin bisa menyesuaikan
 * tanpa deploy lewat Konfigurasi Parameter. Nilai awal sesuai
 * permintaan: Istirahat boleh dicatat mulai 12:00, Kembali mulai 13:00.
 */
return new class extends Migration
{
    private const PLACEHOLDER_DOC = 'CONTOH — menunggu SK Jam Kerja resmi';

    public function up(): void
    {
        $parameters = [
            ['ATT_BREAK_START_TIME', 'Jam paling awal Istirahat boleh dicatat (H:i)', 'string', null, 'Attendance', '12:00', '2026-01-01'],
            ['ATT_BREAK_RETURN_TIME', 'Jam paling awal Kembali dari Istirahat boleh dicatat (H:i)', 'string', null, 'Attendance', '13:00', '2026-01-01'],
        ];

        foreach ($parameters as [$code, $name, $type, $unit, $module, $value, $from]) {
            $parameterId = (string) Str::uuid();

            DB::table('cfg_parameters')->insert([
                'id' => $parameterId,
                'code' => $code,
                'name' => $name,
                'value_type' => $type,
                'unit' => $unit,
                'owner_module' => $module,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);

            DB::table('cfg_parameter_values')->insert([
                'id' => (string) Str::uuid(),
                'parameter_id' => $parameterId,
                'value' => $value,
                'effective_from' => $from,
                'effective_to' => null,
                'source_document' => self::PLACEHOLDER_DOC,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cfg_parameter_values')->whereIn('parameter_id', function ($query) {
            $query->select('id')->from('cfg_parameters')->whereIn('code', ['ATT_BREAK_START_TIME', 'ATT_BREAK_RETURN_TIME']);
        })->delete();

        DB::table('cfg_parameters')->whereIn('code', ['ATT_BREAK_START_TIME', 'ATT_BREAK_RETURN_TIME'])->delete();
    }
};
