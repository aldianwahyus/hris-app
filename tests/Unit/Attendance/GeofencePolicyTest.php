<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\GeoCoordinate;
use App\Modules\Attendance\Domain\GeofencePolicy;
use App\Modules\Attendance\Domain\OutsideGeofence;
use PHPUnit\Framework\TestCase;

final class GeofencePolicyTest extends TestCase
{
    public function test_jarak_dihitung_mendekati_rumus_jarak_bumi_yang_dikenal(): void
    {
        // 0.001 derajat lintang ≈ 111,2 meter di ekuator — nilai acuan yang
        // sudah dikenal luas, dipakai untuk menguji formula Haversine.
        $a = new GeoCoordinate(0.0, 0.0);
        $b = new GeoCoordinate(0.001, 0.0);

        $jarak = GeofencePolicy::distanceMeters($a, $b);

        $this->assertEqualsWithDelta(111.2, $jarak, 1.0);
    }

    public function test_titik_yang_sama_berjarak_nol(): void
    {
        $titik = new GeoCoordinate(-8.5871, 116.1082);

        $this->assertSame(0.0, GeofencePolicy::distanceMeters($titik, $titik));
    }

    public function test_guard_lolos_untuk_titik_dalam_radius(): void
    {
        $kantor = new GeoCoordinate(-8.5871, 116.1082);

        $this->expectNotToPerformAssertions();
        GeofencePolicy::guard($kantor, $kantor, 150);
    }

    public function test_guard_menolak_titik_di_luar_radius(): void
    {
        $kantor = new GeoCoordinate(0.0, 0.0);
        $jauh = new GeoCoordinate(0.01, 0.0); // ≈ 1112 meter

        $this->expectException(OutsideGeofence::class);
        GeofencePolicy::guard($kantor, $jauh, 150);
    }
}
