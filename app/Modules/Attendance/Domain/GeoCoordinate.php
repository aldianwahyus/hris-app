<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use InvalidArgumentException;

/** Satu titik koordinat WGS-84 — dipakai baik untuk lokasi kantor maupun lokasi absen. */
final readonly class GeoCoordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException("Lintang tidak valid: {$latitude}.");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException("Bujur tidak valid: {$longitude}.");
        }
    }
}
