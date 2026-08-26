<?php

declare(strict_types=1);

namespace Tests\Unit\Overtime;

use App\Modules\Overtime\Domain\OvertimeWeek;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OvertimeWeekTest extends TestCase
{
    public function test_tanggal_di_tengah_pekan_menghasilkan_senin_yang_sama(): void
    {
        // 2026-08-19 adalah Rabu.
        $week = OvertimeWeek::containing(new DateTimeImmutable('2026-08-19'));

        $this->assertSame('2026-08-17', $week->toDateString());
    }

    public function test_hari_senin_itu_sendiri_tidak_bergeser(): void
    {
        $week = OvertimeWeek::containing(new DateTimeImmutable('2026-08-17'));

        $this->assertSame('2026-08-17', $week->toDateString());
    }

    public function test_hari_minggu_masuk_minggu_yang_sama_dengan_senin_sebelumnya(): void
    {
        $week = OvertimeWeek::containing(new DateTimeImmutable('2026-08-23'));

        $this->assertSame('2026-08-17', $week->toDateString());
    }
}
