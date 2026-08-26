<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Domain\Pph21Golongan;
use App\Modules\Payroll\Domain\TerRateRepository;
use App\Shared\Temporal\Domain\AsOfDate;
use Tests\TestCase;

/** Verifikasi lookup terhadap data TER sungguhan yang disemai (PMK 168/2023). */
final class TerRateLookupTest extends TestCase
{
    public function test_penghasilan_di_bawah_ambang_golongan_a_tarif_nol(): void
    {
        $rate = app(TerRateRepository::class)->ratePercentFor(Pph21Golongan::A, 5_000_000 * 100, AsOfDate::today());

        $this->assertSame(0.00, $rate);
    }

    public function test_tepat_pada_batas_bawah_lapisan_masuk_lapisan_itu(): void
    {
        // Rp5.400.000 adalah batas ATAS lapisan 1 SEKALIGUS batas BAWAH
        // lapisan 2 — harus masuk lapisan 2 (0,25%), bukan lapisan 1.
        $rate = app(TerRateRepository::class)->ratePercentFor(Pph21Golongan::A, 5_400_000 * 100, AsOfDate::today());

        $this->assertSame(0.25, $rate);
    }

    public function test_golongan_c_ambang_lebih_tinggi_dari_golongan_a(): void
    {
        // Rp5.700.000 -> Golongan A sudah kena 0,50%, Golongan C masih 0%
        // (ambang Golongan C lebih tinggi karena tanggungan maksimal).
        $rateA = app(TerRateRepository::class)->ratePercentFor(Pph21Golongan::A, 5_700_000 * 100, AsOfDate::today());
        $rateC = app(TerRateRepository::class)->ratePercentFor(Pph21Golongan::C, 5_700_000 * 100, AsOfDate::today());

        $this->assertSame(0.50, $rateA);
        $this->assertSame(0.00, $rateC);
    }

    public function test_lapisan_teratas_tanpa_batas_atas(): void
    {
        $rate = app(TerRateRepository::class)->ratePercentFor(Pph21Golongan::A, 2_000_000_000 * 100, AsOfDate::today());

        $this->assertSame(34.00, $rate);
    }
}
