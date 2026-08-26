<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Menjaga jendela waktu Istirahat/Kembali (ATT_BREAK_START_TIME/
 * ATT_BREAK_RETURN_TIME — PARAMETER berversi, bukan konstanta, pola
 * SAMA AttendanceDayPolicy). Hanya BATAS BAWAH yang ditegakkan (boleh
 * dicatat pukul segitu ATAU SETELAHNYA) — tidak ada batas atas, sesuai
 * permintaan ("bisa dimulai dari pukul ...").
 */
final readonly class AttendanceBreakPolicy
{
    public static function guardBreakStart(DateTimeImmutable $now, string $allowedFromTime): void
    {
        if (! self::isAtOrAfter($now, $allowedFromTime)) {
            throw BreakNotYetAllowed::forBreakStart($allowedFromTime);
        }
    }

    public static function guardBreakEnd(DateTimeImmutable $now, string $allowedFromTime): void
    {
        if (! self::isAtOrAfter($now, $allowedFromTime)) {
            throw BreakNotYetAllowed::forBreakEnd($allowedFromTime);
        }
    }

    private static function isAtOrAfter(DateTimeImmutable $now, string $time): bool
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            throw new InvalidArgumentException("Format waktu tidak valid: \"{$time}\" (harus H:i).");
        }

        $threshold = $now->setTime((int) $m[1], (int) $m[2]);

        return $now >= $threshold;
    }
}
