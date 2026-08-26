<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

use App\Core\Domain\Money;
use App\Shared\Temporal\Domain\AsOfDate;

/**
 * Menghitung PPh 21 bulanan memakai metode TER (PMK 168/2023):
 * tarif tunggal × penghasilan bruto bulanan PENUH — bukan progresif
 * berjenjang seperti perhitungan tahunan.
 *
 * "Penghasilan bruto bulanan" WAJIB mencakup seluruh komponen gaji
 * (Imbalan Kerja + Tunjangan Jabatan + Tunjangan Kinerja + ...) — motor
 * ini SENGAJA tidak tahu dari mana angka itu berasal, agar tidak diam-diam
 * dipakai dengan basis yang tidak lengkap (lihat PayslipComponents).
 */
final readonly class Pph21Calculator
{
    public function __construct(private TerRateRepository $rates) {}

    public function compute(Pph21Golongan $golongan, Money $grossMonthly, AsOfDate $asOf): Money
    {
        $ratePercent = $this->rates->ratePercentFor($golongan, $grossMonthly->cents, $asOf);

        return $grossMonthly->percentage($ratePercent);
    }
}
