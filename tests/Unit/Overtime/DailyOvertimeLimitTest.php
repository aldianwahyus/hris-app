<?php

declare(strict_types=1);

namespace Tests\Unit\Overtime;

use App\Modules\Overtime\Domain\DailyOvertimeLimit;
use App\Modules\Overtime\Domain\DailyOvertimeLimitExceeded;
use PHPUnit\Framework\TestCase;

/** BR-LB-04 — maksimal 4 jam per hari untuk Lembur Biasa. */
final class DailyOvertimeLimitTest extends TestCase
{
    public function test_mengizinkan_pengajuan_tepat_di_batas(): void
    {
        (new DailyOvertimeLimit(capHours: 4.0))->guard(4.0);

        $this->addToAssertionCount(1);
    }

    public function test_menolak_pengajuan_melebihi_batas(): void
    {
        $this->expectException(DailyOvertimeLimitExceeded::class);

        (new DailyOvertimeLimit(capHours: 4.0))->guard(4.5);
    }

    public function test_mengizinkan_pengajuan_di_bawah_batas(): void
    {
        (new DailyOvertimeLimit(capHours: 4.0))->guard(2.0);

        $this->addToAssertionCount(1);
    }
}
