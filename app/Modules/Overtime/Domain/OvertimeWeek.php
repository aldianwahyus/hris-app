<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

use DateTimeImmutable;

/**
 * Minggu kerja (Senin—Minggu) yang menaungi satu tanggal lembur —
 * ovt_weekly_quotas selalu mengunci pada hari Senin minggu tersebut.
 */
final readonly class OvertimeWeek
{
    private function __construct(public DateTimeImmutable $mondayDate) {}

    public static function containing(DateTimeImmutable $workDate): self
    {
        $isoWeekday = (int) $workDate->format('N'); // 1=Senin ... 7=Minggu
        $monday = $workDate->modify('-'.($isoWeekday - 1).' days');

        return new self($monday);
    }

    public function toDateString(): string
    {
        return $this->mondayDate->format('Y-m-d');
    }
}
