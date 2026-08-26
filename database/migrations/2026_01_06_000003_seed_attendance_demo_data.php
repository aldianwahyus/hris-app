<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ⚠️ DATA CONTOH — pin pegawai pada mesin fingerprint (mis. 1001,
 * 1002, ...) sekadar agar pemetaan pin -> pegawai dapat diperagakan.
 * Pin sesungguhnya mengikuti pendaftaran di mesin fisik, ditetapkan
 * saat integrasi perangkat nyata dilakukan (lihat Domain/
 * AttendanceDeviceGateway).
 *
 * Turut menyisipkan SATU peristiwa pindai contoh untuk hari ini agar
 * `php artisan absensi:sinkronkan-mesin` punya sesuatu untuk diproses
 * begitu Wave 1 selesai dijalankan, tanpa perlu menulis SQL manual.
 */
return new class extends Migration
{
    private const PINS = [
        '2018.03.0142' => '1001',
        '2015.07.0088' => '1002',
        '2020.01.0231' => '1003',
        '2019.09.0177' => '1004',
        '2021.05.0302' => '1005',
        '2017.11.0119' => '1006',
        '2014.02.0061' => '1007',
    ];

    public function up(): void
    {
        foreach (self::PINS as $nrp => $pin) {
            DB::table('emp_employees')->where('nrp', $nrp)->update(['fingerprint_device_pin' => $pin]);
        }

        $hendraId = DB::table('emp_employees')->where('nrp', '2017.11.0119')->value('id');

        if ($hendraId !== null) {
            DB::table('att_device_scan_logs')->insert([
                'id' => (string) Str::uuid(),
                'device_pin' => self::PINS['2017.11.0119'],
                'scanned_at' => now()->setTime(7, 55),
                'employee_id' => null, // diisi SyncDeviceAttendance saat diproses
                'processed_at' => null,
                'raw_payload' => null,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('att_device_scan_logs')->whereIn('device_pin', array_values(self::PINS))->delete();

        foreach (array_keys(self::PINS) as $nrp) {
            DB::table('emp_employees')->where('nrp', $nrp)->update(['fingerprint_device_pin' => null]);
        }
    }
};
