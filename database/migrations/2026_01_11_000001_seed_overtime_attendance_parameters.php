<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parameter untuk memverifikasi lembur terhadap REALISASI absensi
 * (DEC-37) — bukan jam yang diketik sendiri oleh pemohon. Mengikuti
 * pola ATT_WORK_START_TIME/ATT_LATE_GRACE_MINUTES yang sudah ada.
 *
 * ⚠️ BELUM ADA DOKUMEN RESMI (SK Jam Kerja) — sama seperti
 * 2026_01_06_000002_seed_attendance_parameters, angka di bawah CONTOH
 * sementara.
 */
return new class extends Migration
{
    private const PLACEHOLDER_DOC = 'CONTOH — menunggu SK Jam Kerja resmi';

    public function up(): void
    {
        $parameters = [
            ['ATT_WORK_END_TIME', 'Jam selesai kerja resmi (H:i)', 'string', null, 'Attendance', '17:00', '2026-01-01'],
            ['ATT_OVERTIME_MIN_MINUTES', 'Ambang minimal lewat jam kerja agar dianggap lembur', 'integer', 'menit', 'Attendance', '30', '2026-01-01'],
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
            $query->select('id')->from('cfg_parameters')->whereIn('code', ['ATT_WORK_END_TIME', 'ATT_OVERTIME_MIN_MINUTES']);
        })->delete();

        DB::table('cfg_parameters')->whereIn('code', ['ATT_WORK_END_TIME', 'ATT_OVERTIME_MIN_MINUTES'])->delete();
    }
};
