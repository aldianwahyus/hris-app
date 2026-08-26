<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Domain\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_menghitung_iuran_tht_tanpa_kesalahan_pembulatan(): void
    {
        // Imbalan kerja Rp5.632.000 (contoh dari tabel skala, PG 16 baris 2)
        $imbalanKerja = Money::fromRupiah(5_632_000);

        // Iuran THT 8,05% — 5% pegawai, 3,05% Bank
        $this->assertSame(281_600.0, $imbalanKerja->percentage(5.00)->toRupiah());
        $this->assertSame(171_776.0, $imbalanKerja->percentage(3.05)->toRupiah());
    }

    public function test_perkalian_upah_lembur(): void
    {
        $tarifPerJam = Money::fromRupiah(30_000);

        // Maksimal 4 jam per hari
        $this->assertSame(120_000.0, $tarifPerJam->multiplyBy(4)->toRupiah());
    }

    public function test_penjumlahan_akurat_pada_nilai_pecahan(): void
    {
        // Kasus klasik yang gagal bila memakai float:
        // 0.1 + 0.2 !== 0.3 pada aritmetika titik-mengambang
        $a = Money::fromRupiah(0.1);
        $b = Money::fromRupiah(0.2);

        $this->assertSame(30, $a->add($b)->cents);
        $this->assertSame(0.3, $a->add($b)->toRupiah());
    }

    public function test_format_rupiah(): void
    {
        $this->assertSame('Rp250.000', Money::fromRupiah(250_000)->format());
    }
}
