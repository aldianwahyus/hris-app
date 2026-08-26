<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DomainException;

final class OutsideGeofence extends DomainException
{
    public static function forDistance(float $distanceMeters, int $radiusMeters): self
    {
        return new self(sprintf(
            'Lokasi Anda %d meter dari kantor, di luar radius yang diizinkan (%d meter).',
            (int) round($distanceMeters),
            $radiusMeters,
        ));
    }
}
