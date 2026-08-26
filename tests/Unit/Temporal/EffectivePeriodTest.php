<?php

declare(strict_types=1);

namespace Tests\Unit\Temporal;

use App\Shared\Temporal\Domain\EffectivePeriod;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Effective dating adalah fondasi auditabilitas sistem ini.
 *
 * Skenario nyata yang diuji: tarif lembur berubah melalui SK baru yang
 * berlaku 1 Juli 2026. Menghitung ulang upah lembur bulan Mei 2026 harus
 * memakai tarif LAMA — bukan tarif terkini. Kegagalan di sini berarti
 * angka pada laporan audit tidak akan cocok dengan yang dibayarkan.
 */
final class EffectivePeriodTest extends TestCase
{
    private EffectivePeriod $skLama;

    private EffectivePeriod $skBaru;

    protected function setUp(): void
    {
        $this->skLama = new EffectivePeriod(
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2026-06-30'),
        );

        $this->skBaru = EffectivePeriod::startingFrom(new DateTimeImmutable('2026-07-01'));
    }

    public function test_transaksi_lama_memakai_ketentuan_yang_berlaku_saat_itu(): void
    {
        $lemburMei = new DateTimeImmutable('2026-05-15');

        $this->assertTrue($this->skLama->contains($lemburMei));
        $this->assertFalse($this->skBaru->contains($lemburMei));
    }

    public function test_transaksi_baru_memakai_ketentuan_terkini(): void
    {
        $lemburAgustus = new DateTimeImmutable('2026-08-15');

        $this->assertTrue($this->skBaru->contains($lemburAgustus));
        $this->assertFalse($this->skLama->contains($lemburAgustus));
    }

    /** Batas periode rawan kesalahan selisih satu hari. */
    public function test_batas_periode_tepat_pada_hari_pergantian(): void
    {
        $this->assertTrue(
            $this->skLama->contains(new DateTimeImmutable('2026-06-30')),
            'Hari terakhir keberlakuan harus masih tercakup.'
        );

        $this->assertTrue($this->skBaru->contains(new DateTimeImmutable('2026-07-01')));
        $this->assertFalse($this->skLama->contains(new DateTimeImmutable('2026-07-01')));
    }

    public function test_mendeteksi_periode_tumpang_tindih(): void
    {
        $this->assertFalse($this->skLama->overlaps($this->skBaru));

        $periodeSalah = EffectivePeriod::startingFrom(new DateTimeImmutable('2026-06-01'));

        $this->assertTrue(
            $this->skLama->overlaps($periodeSalah),
            'Dua ketentuan berlaku bersamaan harus terdeteksi — dicegah constraint EXCLUDE di basis data.'
        );
    }

    public function test_menutup_periode_saat_ketentuan_baru_terbit(): void
    {
        $ditutup = $this->skBaru->closedBefore(new DateTimeImmutable('2027-01-01'));

        $this->assertSame('2026-12-31', $ditutup->effectiveTo->format('Y-m-d'));
    }

    public function test_menolak_periode_dengan_tanggal_terbalik(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EffectivePeriod(
            new DateTimeImmutable('2026-07-01'),
            new DateTimeImmutable('2026-01-01'),
        );
    }
}
