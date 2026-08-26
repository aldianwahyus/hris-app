<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\AttendanceBreakPolicy;
use App\Modules\Attendance\Domain\BreakNotYetAllowed;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AttendanceBreakPolicyTest extends TestCase
{
    public function test_istirahat_sebelum_jam_yang_diizinkan_ditolak(): void
    {
        $this->expectException(BreakNotYetAllowed::class);

        AttendanceBreakPolicy::guardBreakStart(new DateTimeImmutable('2026-08-18 11:59:00'), '12:00');
    }

    public function test_istirahat_tepat_pada_jam_yang_diizinkan_berhasil(): void
    {
        $this->expectNotToPerformAssertions();

        AttendanceBreakPolicy::guardBreakStart(new DateTimeImmutable('2026-08-18 12:00:00'), '12:00');
    }

    public function test_istirahat_setelah_jam_yang_diizinkan_berhasil(): void
    {
        $this->expectNotToPerformAssertions();

        AttendanceBreakPolicy::guardBreakStart(new DateTimeImmutable('2026-08-18 14:30:00'), '12:00');
    }

    public function test_kembali_sebelum_jam_yang_diizinkan_ditolak(): void
    {
        $this->expectException(BreakNotYetAllowed::class);

        AttendanceBreakPolicy::guardBreakEnd(new DateTimeImmutable('2026-08-18 12:59:00'), '13:00');
    }

    public function test_kembali_tepat_pada_jam_yang_diizinkan_berhasil(): void
    {
        $this->expectNotToPerformAssertions();

        AttendanceBreakPolicy::guardBreakEnd(new DateTimeImmutable('2026-08-18 13:00:00'), '13:00');
    }

    public function test_kembali_setelah_jam_yang_diizinkan_berhasil(): void
    {
        $this->expectNotToPerformAssertions();

        AttendanceBreakPolicy::guardBreakEnd(new DateTimeImmutable('2026-08-18 15:00:00'), '13:00');
    }

    public function test_format_jam_tidak_valid_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AttendanceBreakPolicy::guardBreakStart(new DateTimeImmutable('2026-08-18 12:00:00'), '12:0');
    }

    public function test_pesan_error_menyebutkan_jam_yang_diizinkan(): void
    {
        try {
            AttendanceBreakPolicy::guardBreakStart(new DateTimeImmutable('2026-08-18 08:00:00'), '12:00');
            $this->fail('Seharusnya melempar BreakNotYetAllowed.');
        } catch (BreakNotYetAllowed $e) {
            $this->assertStringContainsString('12:00', $e->getMessage());
        }
    }
}
