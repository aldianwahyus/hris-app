<?php

declare(strict_types=1);

namespace App\Modules\Leave\Domain;

/**
 * Satu kantong saldo cuti. `consumptionOrder` menentukan urutan
 * pemakaian — jatah tahun berjalan (order 1) HARUS habis lebih dulu
 * sebelum sisa tahun lalu (order 2) tersentuh, karena hanya kantong
 * tahun berjalan yang memberi hak bekal cuti.
 */
final readonly class LeaveBucket
{
    public function __construct(
        public BucketType $type,
        public float $quotaDays,
        public float $usedDays,
        public int $consumptionOrder,
    ) {}

    public function remaining(): float
    {
        return max(0.0, $this->quotaDays - $this->usedDays);
    }
}
