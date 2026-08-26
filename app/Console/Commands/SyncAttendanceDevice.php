<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Attendance\Application\SyncDeviceAttendance;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * Menyerap peristiwa pindai mesin fingerprint secara berkala.
 *
 * Jendela mundur 2 hari sekadar pagar pengaman (adaptor sungguhan
 * kelak mungkin tidak punya konsep "processed_at" sendiri) — stub saat
 * ini sudah menyaring lewat att_device_scan_logs.processed_at, jadi
 * tidak akan memproses ulang peristiwa yang sama.
 */
final class SyncAttendanceDevice extends Command
{
    protected $signature = 'absensi:sinkronkan-mesin';

    protected $description = 'Mengambil data pindai dari mesin fingerprint dan mencatatnya sebagai kehadiran';

    public function handle(SyncDeviceAttendance $sync): int
    {
        $result = $sync->handle(new DateTimeImmutable('-2 days'));

        $this->info("Sinkronisasi selesai: {$result->matched} cocok, {$result->unmatched} pin tidak dikenal.");

        return self::SUCCESS;
    }
}
