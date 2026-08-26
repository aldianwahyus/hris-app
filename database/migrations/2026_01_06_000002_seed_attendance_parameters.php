<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parameter kebijakan Absensi — BUKAN konstanta (sejalan dengan pola
 * OVT_xxx dan LEAVE_xxx yang sudah ada).
 *
 * ⚠️ BELUM ADA DOKUMEN RESMI (SK Jam Kerja) yang menjadi dasarnya —
 * angka di bawah adalah CONTOH sementara agar AttendanceDayPolicy
 * dapat diperagakan ujung ke ujung. source_document sengaja diisi
 * penanda ini, BUKAN nomor SK sungguhan, agar auditor tidak salah
 * kira nilai ini sudah disahkan.
 */
return new class extends Migration
{
    private const PLACEHOLDER_DOC = 'CONTOH — menunggu SK Jam Kerja resmi';

    public function up(): void
    {
        $parameters = [
            ['ATT_WORK_START_TIME', 'Jam mulai kerja resmi (H:i)', 'string', null, 'Attendance', '08:00', '2026-01-01'],
            ['ATT_LATE_GRACE_MINUTES', 'Toleransi keterlambatan sebelum ditandai telat', 'integer', 'menit', 'Attendance', '15', '2026-01-01'],
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
            $query->select('id')->from('cfg_parameters')->whereIn('code', ['ATT_WORK_START_TIME', 'ATT_LATE_GRACE_MINUTES']);
        })->delete();

        DB::table('cfg_parameters')->whereIn('code', ['ATT_WORK_START_TIME', 'ATT_LATE_GRACE_MINUTES'])->delete();
    }
};
