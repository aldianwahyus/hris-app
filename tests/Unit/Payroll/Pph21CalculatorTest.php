<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Core\Domain\Money;
use App\Modules\Payroll\Domain\Pph21Calculator;
use App\Modules\Payroll\Domain\Pph21Golongan;
use App\Modules\Payroll\Domain\TerRateRepository;
use App\Shared\Temporal\Domain\AsOfDate;
use PHPUnit\Framework\TestCase;

final class Pph21CalculatorTest extends TestCase
{
    public function test_menghitung_pph21_dari_tarif_tunggal_bukan_progresif(): void
    {
        $rates = new class implements TerRateRepository
        {
            public function ratePercentFor(Pph21Golongan $golongan, int $grossMonthlyCents, AsOfDate $asOf): float
            {
                return 5.00; // tarif tetap untuk uji ini
            }
        };

        $calculator = new Pph21Calculator($rates);
        $pph21 = $calculator->compute(Pph21Golongan::A, Money::fromRupiah(20_000_000), AsOfDate::today());

        // TER: tarif tunggal x SELURUH penghasilan bruto, bukan berjenjang.
        $this->assertSame(100_000_000, $pph21->cents); // 5% x 20.000.000 = 1.000.000
    }
}
