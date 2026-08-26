<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

/**
 * Validasi lokasi absen GPS terhadap radius kantor (geofence_radius_m
 * pada md_offices). Jarak dihitung dengan formula Haversine — cukup
 * akurat untuk radius ratusan meter yang dipakai di sini, tanpa
 * ketergantungan ekstensi geospasial basis data.
 */
final readonly class GeofencePolicy
{
    private const EARTH_RADIUS_METERS = 6_371_000.0;

    public static function distanceMeters(GeoCoordinate $a, GeoCoordinate $b): float
    {
        $lat1 = deg2rad($a->latitude);
        $lat2 = deg2rad($b->latitude);
        $deltaLat = deg2rad($b->latitude - $a->latitude);
        $deltaLon = deg2rad($b->longitude - $a->longitude);

        $h = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($h)));
    }

    /** @throws OutsideGeofence bila titik berada di luar radius kantor. */
    public static function guard(GeoCoordinate $officeCenter, GeoCoordinate $point, int $radiusMeters): void
    {
        $distance = self::distanceMeters($officeCenter, $point);

        if ($distance > $radiusMeters) {
            throw OutsideGeofence::forDistance($distance, $radiusMeters);
        }
    }
}
