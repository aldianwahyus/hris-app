<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Core\Domain\Money;
use App\Modules\Payroll\Domain\GajiPokokCalculator;
use PHPUnit\Framework\TestCase;

/**
 * BPP/137/03/64/2026 §H.1-2 — iuran Pensiun/THT murni berbasis Imbalan
 * Kerja (Tunjangan Jabatan/Penyesuaian TIDAK ikut basis iuran, hanya
 * menambah takeHomePartial — lihat SEC-2026-08-TJ).
 */
final class GajiPokokCalculatorTest extends TestCase
{
    public function test_menghitung_iuran_dan_sebagian_gaji_bersih(): void
    {
        // Person Grade 8 baris 1 = Rp2.050.000 (Lampiran II).
        $calculator = new GajiPokokCalculator(
            pensionEmployeePct: 7.00,
            thtTotalPct: 8.05,
            thtEmployeePct: 5.00,
        );

        $components = $calculator->compute(
            Money::fromRupiah(2_050_000),
            Money::fromRupiah(500_000), // Tunjangan Jabatan
            Money::fromRupiah(50_000),  // Tunjangan Penyesuaian
        );

        $this->assertSame(205_000_000, $components->imbalanKerja->cents);
        $this->assertSame(50_000_000, $components->tunjanganJabatan->cents);
        $this->assertSame(5_000_000, $components->tunjanganPenyesuaian->cents);
        $this->assertSame(14_350_000, $components->iuranPensiunPegawai->cents); // 7% x 2.050.000 — BUKAN dari (Imbalan+Tunjangan)
        $this->assertSame(10_250_000, $components->iuranThtPegawai->cents);     // 5% x 2.050.000
        $this->assertSame(6_252_500, $components->iuranThtBank->cents);         // 3,05% x 2.050.000
        // (2.050.000 + 500.000 + 50.000) - 143.500 - 102.500 = 2.354.000
        $this->assertSame(235_400_000, $components->takeHomePartial->cents);
    }

    public function test_tunjangan_nol_tidak_mengubah_perhitungan_lama(): void
    {
        $calculator = new GajiPokokCalculator(7.00, 8.05, 5.00);

        $components = $calculator->compute(Money::fromRupiah(2_050_000), Money::zero(), Money::zero());

        $this->assertSame(180_400_000, $components->takeHomePartial->cents);
    }

    public function test_komponen_yang_belum_lengkap_selalu_tercantum(): void
    {
        $calculator = new GajiPokokCalculator(7.00, 8.05, 5.00);
        $components = $calculator->compute(Money::fromRupiah(1_000_000), Money::zero(), Money::zero());

        $this->assertFalse($components->iuranPensiunBankDiketahui());
        $this->assertNotEmpty($components->pendingComponents());
        // Tunjangan Jabatan SUDAH tersedia sejak SEC-2026-08-TJ — tidak lagi pending.
        $this->assertNotContains('Tunjangan Jabatan (menunggu Lampiran III)', $components->pendingComponents());
        $this->assertContains('Tunjangan Kinerja (menunggu Lampiran III)', $components->pendingComponents());
    }
}
