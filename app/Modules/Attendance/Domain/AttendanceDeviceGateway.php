<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DateTimeImmutable;

/**
 * Port ke mesin absensi fingerprint — merek/protokol perangkat
 * SESUNGGUHNYA belum ditentukan, jadi antarmuka ini sengaja generik
 * ("ambil data sejak waktu tertentu") alih-alih mengasumsikan satu
 * SDK tertentu (ZKTeco/Fingerspot/Solution X100 punya protokol yang
 * berbeda-beda).
 *
 * Implementasi Infrastructure/StubAttendanceDeviceGateway berlaku
 * sementara — menunjang alur sinkronisasi ujung-ke-ujung tanpa
 * berpura-pura sudah tersambung ke perangkat sungguhan. Ganti dengan
 * adaptor SDK yang sesuai begitu merek mesin dipastikan.
 */
interface AttendanceDeviceGateway
{
    /** @return array<int, DeviceScanEvent> */
    public function fetchScansSince(DateTimeImmutable $since): array;

    /**
     * Menandai peristiwa sebagai sudah diproses agar tidak diambil ulang.
     *
     * @param  array<int, string>  $logIds
     */
    public function acknowledge(array $logIds): void;
}
