<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

/**
 * Jenis perjalanan dinas (BPP/442/03/64/2026 §II.A). Menentukan pola
 * tarif YANG BERBEDA-BEDA — jangan disamaratakan:
 *   - JarakPendek: tarif seragam per pita radius, TANPA uang saku/hotel.
 *   - JarakJauh*: uang makan+saku per jenjang jabatan, hotel/angkutan
 *     ber-plafon (at-cost).
 *   - LuarNegeri: uang makan+saku dalam USD, TANPA plafon hotel/angkutan
 *     resmi (at-cost murni, realisasi lewat invoice).
 *   - Pindah/Detasir: uang makan+saku per jenjang (4 jenjang saja, TIDAK
 *     ada baris Non Staff pada tabel BPP), TANPA plafon hotel/angkutan.
 */
enum TripCategory: string
{
    case JarakPendek = 'jarak_pendek';
    case JarakJauhKeluarProvinsi = 'jarak_jauh_keluar_provinsi';
    case JarakJauhDalamProvinsi = 'jarak_jauh_dalam_provinsi';
    case LuarNegeri = 'luar_negeri';
    case Pindah = 'pindah';
    case Detasir = 'detasir';

    public function label(): string
    {
        return match ($this) {
            self::JarakPendek => 'Jarak Pendek (dalam 1 pulau)',
            self::JarakJauhKeluarProvinsi => 'Jarak Jauh — Keluar Wilayah Provinsi NTB',
            self::JarakJauhDalamProvinsi => 'Jarak Jauh — Di Dalam Wilayah Provinsi NTB',
            self::LuarNegeri => 'Luar Negeri',
            self::Pindah => 'Pindah Tugas',
            self::Detasir => 'Detasir',
        };
    }

    public function mataUang(): string
    {
        return $this === self::LuarNegeri ? 'USD' : 'IDR';
    }

    /** Hotel/angkutan setempat/transportasi tujuan ber-plafon HANYA untuk dua kategori ini (§II.B.5, 6, 7, 8). */
    public function memilikiPlafonAtCost(): bool
    {
        return in_array($this, [self::JarakJauhKeluarProvinsi, self::JarakJauhDalamProvinsi], true);
    }

    /** Jarak Pendek tarifnya per pita radius, BUKAN per jenjang jabatan (Bab III.A.1). */
    public function berbasisRadius(): bool
    {
        return $this === self::JarakPendek;
    }

    /**
     * Ketentuan transit H-1/H+1 (§III.B.3) dan konsumsi ditanggung
     * panitia (§III.B.4) — Bab III.B BPP secara eksplisit hanya berlaku
     * untuk perjalanan Jarak Jauh, TIDAK ADA aturan setara untuk Jarak
     * Pendek (tarif seragam per radius, bukan per hari) maupun Luar
     * Negeri/Pindah/Detasir (bab terpisah, tanpa rujukan §III.B sama
     * sekali). Mencentang komponen ini di luar dua kategori Jarak Jauh
     * adalah kesalahan input, bukan pilihan yang sah (bug ditemukan
     * lewat audit kode — SubmitSppdMemoGroup sebelumnya tidak menyaring
     * ini sama sekali).
     */
    public function berlakuKetentuanTransitDanKonsumsiBpp(): bool
    {
        return in_array($this, [self::JarakJauhKeluarProvinsi, self::JarakJauhDalamProvinsi], true);
    }
}
