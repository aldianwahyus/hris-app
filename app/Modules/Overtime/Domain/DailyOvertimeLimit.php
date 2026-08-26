<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

/**
 * Maksimal 4 jam per hari untuk Lembur Biasa (BR-LB-04) — batas
 * PER PENGAJUAN, terpisah dari plafon mingguan (WeeklyOvertimeQuota).
 * Tidak berlaku untuk Crash Program (lumpsum harian, bukan berbasis
 * jam) maupun Shift/Piket (mengikuti jadwal piket, BR-LB-13) — pemanggil
 * yang memutuskan kapan batas ini relevan.
 *
 * Angka BUKAN konstanta — parameter berversi OVT_DAILY_MAX_HOURS.
 */
final readonly class DailyOvertimeLimit
{
    public function __construct(private float $capHours) {}

    public function guard(float $requestedHours): void
    {
        if ($requestedHours > $this->capHours) {
            throw DailyOvertimeLimitExceeded::forRequest($requestedHours, $this->capHours);
        }
    }
}
