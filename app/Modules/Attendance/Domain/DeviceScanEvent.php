<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DateTimeImmutable;

/**
 * Satu peristiwa pindai dari mesin fingerprint, SUDAH diterjemahkan
 * dari format mentah perangkat — pin masih milik mesin (belum tentu
 * NRP), pemetaan ke pegawai dilakukan pemanggil (Application layer)
 * lewat emp_employees.fingerprint_device_pin.
 */
final readonly class DeviceScanEvent
{
    public function __construct(
        public string $logId,
        public string $devicePin,
        public DateTimeImmutable $scannedAt,
    ) {}
}
