<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DomainException;

/** Istirahat/Kembali dicoba sebelum jam paling awal yang diizinkan (ATT_BREAK_START_TIME/ATT_BREAK_RETURN_TIME). */
final class BreakNotYetAllowed extends DomainException
{
    public static function forBreakStart(string $allowedFrom): self
    {
        return new self("Istirahat baru bisa dicatat mulai pukul {$allowedFrom}.");
    }

    public static function forBreakEnd(string $allowedFrom): self
    {
        return new self("Kembali dari istirahat baru bisa dicatat mulai pukul {$allowedFrom}.");
    }
}
