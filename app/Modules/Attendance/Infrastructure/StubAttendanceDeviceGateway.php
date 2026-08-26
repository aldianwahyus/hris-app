<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure;

use App\Modules\Attendance\Domain\AttendanceDeviceGateway;
use App\Modules\Attendance\Domain\DeviceScanEvent;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Adaptor SEMENTARA untuk AttendanceDeviceGateway — merek/protokol
 * mesin fingerprint sesungguhnya belum ditentukan (lihat Domain/
 * AttendanceDeviceGateway).
 *
 * "Ambil data" di sini berarti membaca att_device_scan_logs — tabel
 * singgah yang berperan sebagai kotak masuk mesin. Ini BUKAN simulasi
 * berpura-pura: begitu adaptor SDK sungguhan tersedia, ia cukup
 * MENULIS ke tabel yang sama (atau kelas ini diganti total) tanpa
 * mengubah Application/SyncDeviceAttendance sama sekali — itulah
 * gunanya port ini.
 */
final class StubAttendanceDeviceGateway implements AttendanceDeviceGateway
{
    /** @return array<int, DeviceScanEvent> */
    public function fetchScansSince(DateTimeImmutable $since): array
    {
        return DB::table('att_device_scan_logs')
            ->whereNull('processed_at')
            ->where('scanned_at', '>=', $since)
            ->orderBy('scanned_at')
            ->get()
            ->map(fn ($row) => new DeviceScanEvent(
                logId: $row->id,
                devicePin: $row->device_pin,
                scannedAt: new DateTimeImmutable($row->scanned_at),
            ))
            ->all();
    }

    /** @param array<int, string> $logIds */
    public function acknowledge(array $logIds): void
    {
        if ($logIds === []) {
            return;
        }

        DB::table('att_device_scan_logs')->whereIn('id', $logIds)->update(['processed_at' => now()]);
    }
}
