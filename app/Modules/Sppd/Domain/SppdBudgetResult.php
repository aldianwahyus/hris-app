<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

use App\Core\Domain\Money;

/**
 * Seluruh komponen anggaran SPPD — SEPENUHNYA dihitung sistem dari
 * jenjang jabatan + kategori perjalanan + lama hari, TIDAK ADA yang
 * diisi manual oleh pemohon (BPP/442/03/64/2026).
 *
 * `uangMakan`/`uangSaku` adalah Lumpsum sesungguhnya (BPP §I.D.23).
 * `hotel`/`angkutanSetempat`/`transportasiTujuan` SECARA BPP bersifat
 * at-cost (§I.D.24, dibayar sesuai tagihan riil setelah perjalanan) —
 * nilai di sini adalah PLAFON MAKSIMAL yang dipakai sebagai anggaran
 * rencana/batas persetujuan, BUKAN klaim bahwa jumlah ini yang akan
 * dibayarkan. Realisasi sesuai invoice adalah tahap lanjutan yang
 * belum dibangun.
 */
final readonly class SppdBudgetResult
{
    public function __construct(
        public Money $uangMakan,
        public Money $uangSaku,
        public ?Money $hotel,
        public ?Money $angkutanSetempat,
        public ?Money $transportasiTujuan,
        public string $mataUang,
    ) {}

    /** Uang Makan + Uang Saku — Lumpsum murni per BPP §I.D.23. */
    public function totalLumpsum(): Money
    {
        return $this->uangMakan->add($this->uangSaku);
    }

    /** Plafon Hotel + Angkutan Setempat + Transportasi Tujuan (yang berlaku). */
    public function totalPlafonAtCost(): Money
    {
        $total = Money::zero();

        foreach ([$this->hotel, $this->angkutanSetempat, $this->transportasiTujuan] as $komponen) {
            if ($komponen !== null) {
                $total = $total->add($komponen);
            }
        }

        return $total;
    }

    public function totalKeseluruhan(): Money
    {
        return $this->totalLumpsum()->add($this->totalPlafonAtCost());
    }
}
