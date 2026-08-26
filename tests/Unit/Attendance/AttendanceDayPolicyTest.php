<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\AttendanceDayPolicy;
use App\Modules\Attendance\Domain\AttendanceStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AttendanceDayPolicyTest extends TestCase
{
    public function test_masuk_sebelum_jam_kerja_berstatus_hadir(): void
    {
        $status = AttendanceDayPolicy::determineCheckInStatus(
            new DateTimeImmutable('2026-08-18 07:50:00'), '08:00', 15,
        );

        $this->assertSame(AttendanceStatus::Hadir, $status);
    }

    public function test_masuk_dalam_toleransi_tetap_hadir(): void
    {
        $status = AttendanceDayPolicy::determineCheckInStatus(
            new DateTimeImmutable('2026-08-18 08:15:00'), '08:00', 15,
        );

        $this->assertSame(AttendanceStatus::Hadir, $status);
    }

    public function test_masuk_satu_menit_melewati_toleransi_berstatus_telat(): void
    {
        $status = AttendanceDayPolicy::determineCheckInStatus(
            new DateTimeImmutable('2026-08-18 08:16:00'), '08:00', 15,
        );

        $this->assertSame(AttendanceStatus::Telat, $status);
    }

    public function test_masuk_jauh_setelah_jam_kerja_berstatus_telat(): void
    {
        $status = AttendanceDayPolicy::determineCheckInStatus(
            new DateTimeImmutable('2026-08-18 10:00:00'), '08:00', 15,
        );

        $this->assertSame(AttendanceStatus::Telat, $status);
    }

    public function test_format_jam_kerja_tidak_valid_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AttendanceDayPolicy::determineCheckInStatus(new DateTimeImmutable('2026-08-18 08:00:00'), '8:00', 15);
    }
}
