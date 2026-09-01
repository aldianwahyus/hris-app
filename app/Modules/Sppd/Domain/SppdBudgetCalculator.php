<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

use App\Core\Domain\Money;
use App\Shared\Temporal\Domain\AsOfDate;
use InvalidArgumentException;

/**
 * Menghitung SELURUH anggaran SPPD — tidak ada kolom uang yang diisi
 * pemohon secara bebas (BPP/442/03/64/2026). Uang Makan/Saku dan
 * plafon Hotel/Angkutan Setempat dikalikan lama hari (§II.B.5, §II.B.7
 * — "plafond maksimal PER HARI/orang"); Transportasi ke Tempat Tujuan
 * TIDAK dikalikan hari — satu kali PP per perjalanan (§II.B.8, tidak
 * ada frasa "per hari" pada BPP, berbeda dari dua komponen sebelumnya).
 *
 * CATATAN JUJUR: aturan H-1/H+1 dengan pembayaran 25% pada hari
 * transit (BPP §III.B.3) TIDAK diterapkan — seluruh hari dihitung
 * tarif penuh. Penyederhanaan yang diketahui, bukan kelalaian.
 */
final readonly class SppdBudgetCalculator
{
    public function __construct(private SppdTariffRepository $tariffs) {}

    public function compute(
        TripCategory $category,
        ?JabatanTier $tier,
        ?RadiusBand $radiusBand,
        int $totalDays,
        AsOfDate $asOf,
    ): SppdBudgetResult {
        if ($totalDays < 1) {
            throw new InvalidArgumentException('Jumlah hari perjalanan dinas harus minimal 1.');
        }

        if ($category->berbasisRadius()) {
            if ($radiusBand === null) {
                throw new InvalidArgumentException('Pita radius wajib diisi untuk perjalanan dinas jarak pendek.');
            }

            $uangMakan = $this->tariffs
                ->amountFor(SppdTariffComponent::UangMakan, $category, null, $radiusBand, $asOf)
                ->multiplyBy($totalDays);

            // Jarak Pendek tidak mengenal uang saku/hotel/angkutan (§III.A)
            // — pulang-pergi dalam hari yang sama, bukan menginap.
            return new SppdBudgetResult($uangMakan, Money::zero(), null, null, null, null, $category->mataUang());
        }

        if ($tier === null) {
            throw new InvalidArgumentException('Jenjang jabatan wajib diisi untuk kategori perjalanan dinas ini.');
        }

        $uangMakan = $this->tariffs->amountFor(SppdTariffComponent::UangMakan, $category, $tier, null, $asOf)->multiplyBy($totalDays);
        $uangSaku = $this->tariffs->amountFor(SppdTariffComponent::UangSaku, $category, $tier, null, $asOf)->multiplyBy($totalDays);

        $hotel = null;
        $hotelKompensasi = null;
        $angkutanSetempat = null;
        $transportasiTujuan = null;

        if ($category->memilikiPlafonAtCost()) {
            $hotel = $this->tariffs->amountFor(SppdTariffComponent::HotelCap, $category, $tier, null, $asOf)->multiplyBy($totalDays);
            $hotelKompensasi = $this->tariffs->amountFor(SppdTariffComponent::HotelCompensation, $category, $tier, null, $asOf)->multiplyBy($totalDays);
            $angkutanSetempat = $this->tariffs->amountFor(SppdTariffComponent::LocalTransportCap, $category, $tier, null, $asOf)->multiplyBy($totalDays);
            // TIDAK dikalikan hari — satu kali PP (lihat catatan kelas).
            $transportasiTujuan = $this->tariffs->amountFor(SppdTariffComponent::DestinationTransportCap, $category, $tier, null, $asOf);
        }

        return new SppdBudgetResult($uangMakan, $uangSaku, $hotel, $hotelKompensasi, $angkutanSetempat, $transportasiTujuan, $category->mataUang());
    }
}
