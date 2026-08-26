<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Menentukan apakah jam pulang aktual membuktikan lembur pada satu
 * hari — dan bila ya, berapa jamnya — dibandingkan jam kerja resmi
 * (ATT_WORK_END_TIME) + ambang minimal (ATT_OVERTIME_MIN_MINUTES).
 *
 * jam kerja resmi DIJANGKARKAN pada tanggal check-in ($checkInAt),
 * BUKAN tanggal check-out — lembur panjang yang pulang lewat tengah
 * malam membuat check-out sudah berada di kalender hari berikutnya;
 * menjangkarkan pada tanggal check-out akan membuat jam kerja resmi
 * ikut bergeser ke hari itu dan salah menyimpulkan "tidak ada lembur".
 *
 * Ambang minimal HANYA menggerbangi ADA/TIDAKNYA lembur (menghindari
 * klaim sepele akibat selisih beberapa menit) — jam yang dilaporkan
 * tetap selisih PENUH dari jam kerja resmi, bukan dikurangi ambang,
 * sejalan dengan pola AttendanceDayPolicy (ambang keterlambatan tidak
 * menggeser jam masuk yang dicatat).
 */
final readonly class AttendanceOvertimePolicy
{
    public static function determineOvertimeHours(
        DateTimeImmutable $checkInAt,
        DateTimeImmutable $checkOutAt,
        string $workEndTime,
        int $minQualifyingMinutes,
    ): ?float {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $workEndTime, $m)) {
            throw new InvalidArgumentException("Format ATT_WORK_END_TIME tidak valid: \"{$workEndTime}\" (harus H:i).");
        }

        $scheduledEnd = $checkInAt->setTime((int) $m[1], (int) $m[2]);
        $qualifyingThreshold = $scheduledEnd->modify("+{$minQualifyingMinutes} minutes");

        if ($checkOutAt <= $qualifyingThreshold) {
            return null;
        }

        $minutesBeyondSchedule = (int) round(($checkOutAt->getTimestamp() - $scheduledEnd->getTimestamp()) / 60);

        return round($minutesBeyondSchedule / 60, 1);
    }
}
