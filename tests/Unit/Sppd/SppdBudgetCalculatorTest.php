<?php

declare(strict_types=1);

namespace Tests\Unit\Sppd;

use App\Core\Domain\Money;
use App\Modules\Sppd\Domain\JabatanTier;
use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\SppdBudgetCalculator;
use App\Modules\Sppd\Domain\SppdTariffComponent;
use App\Modules\Sppd\Domain\SppdTariffRepository;
use App\Modules\Sppd\Domain\TripCategory;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SppdBudgetCalculatorTest extends TestCase
{
    private function calculator(): SppdBudgetCalculator
    {
        $tariffs = new class implements SppdTariffRepository
        {
            public function amountFor(
                SppdTariffComponent $component,
                TripCategory $category,
                ?JabatanTier $tier,
                ?RadiusBand $radiusBand,
                AsOfDate $asOf,
            ): Money {
                return match ($component) {
                    SppdTariffComponent::UangMakan => $radiusBand !== null ? Money::fromRupiah(150_000) : Money::fromRupiah(200_000),
                    SppdTariffComponent::UangSaku => Money::fromRupiah(100_000),
                    SppdTariffComponent::HotelCap => Money::fromRupiah(750_000),
                    SppdTariffComponent::LocalTransportCap => Money::fromRupiah(300_000),
                    SppdTariffComponent::DestinationTransportCap => Money::fromRupiah(1_200_000),
                    SppdTariffComponent::HotelCompensation => Money::zero(),
                };
            }
        };

        return new SppdBudgetCalculator($tariffs);
    }

    private function asOf(): AsOfDate
    {
        return AsOfDate::on(new DateTimeImmutable('2026-02-01'));
    }

    public function test_jarak_pendek_hanya_uang_makan_dikali_hari_tanpa_saku_hotel(): void
    {
        $result = $this->calculator()->compute(TripCategory::JarakPendek, null, RadiusBand::Km30To100, 2, $this->asOf());

        $this->assertSame(300_000_00, $result->uangMakan->cents);
        $this->assertTrue($result->uangSaku->isZero());
        $this->assertNull($result->hotel);
        $this->assertNull($result->angkutanSetempat);
        $this->assertNull($result->transportasiTujuan);
        $this->assertSame('IDR', $result->mataUang);
    }

    public function test_jarak_pendek_tanpa_pita_radius_dilempar_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator()->compute(TripCategory::JarakPendek, null, null, 2, $this->asOf());
    }

    public function test_jarak_jauh_keluar_provinsi_menghitung_semua_komponen_hotel_angkutan_dikali_hari_transport_tidak(): void
    {
        $result = $this->calculator()->compute(
            TripCategory::JarakJauhKeluarProvinsi,
            JabatanTier::DeptHeadSbmMgr,
            null,
            3,
            $this->asOf(),
        );

        $this->assertSame(600_000_00, $result->uangMakan->cents);
        $this->assertSame(300_000_00, $result->uangSaku->cents);
        $this->assertSame(2_250_000_00, $result->hotel?->cents);
        $this->assertSame(900_000_00, $result->angkutanSetempat?->cents);
        // Transportasi tujuan TIDAK dikalikan hari — satu kali PP.
        $this->assertSame(1_200_000_00, $result->transportasiTujuan?->cents);
    }

    public function test_jarak_jauh_dalam_provinsi_juga_memiliki_plafon_at_cost(): void
    {
        $result = $this->calculator()->compute(
            TripCategory::JarakJauhDalamProvinsi,
            JabatanTier::TeamLeaderSpvOfficerStaff,
            null,
            1,
            $this->asOf(),
        );

        $this->assertNotNull($result->hotel);
        $this->assertNotNull($result->angkutanSetempat);
        $this->assertNotNull($result->transportasiTujuan);
    }

    public function test_luar_negeri_tanpa_plafon_hotel_angkutan_transportasi(): void
    {
        $result = $this->calculator()->compute(TripCategory::LuarNegeri, JabatanTier::Sevp, null, 4, $this->asOf());

        $this->assertNull($result->hotel);
        $this->assertNull($result->angkutanSetempat);
        $this->assertNull($result->transportasiTujuan);
        $this->assertSame('USD', $result->mataUang);
    }

    public function test_pindah_dan_detasir_tanpa_plafon_at_cost(): void
    {
        $pindah = $this->calculator()->compute(TripCategory::Pindah, JabatanTier::PejabatEksekutif, null, 2, $this->asOf());
        $detasir = $this->calculator()->compute(TripCategory::Detasir, JabatanTier::PejabatEksekutif, null, 2, $this->asOf());

        $this->assertNull($pindah->hotel);
        $this->assertNull($detasir->hotel);
    }

    public function test_kategori_non_radius_tanpa_jenjang_jabatan_dilempar_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator()->compute(TripCategory::JarakJauhKeluarProvinsi, null, null, 2, $this->asOf());
    }

    public function test_total_hari_kurang_dari_satu_dilempar_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator()->compute(TripCategory::JarakPendek, null, RadiusBand::Km30To100, 0, $this->asOf());
    }
}
