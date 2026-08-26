<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

use App\Core\Domain\Money;

/**
 * Menghitung komponen slip gaji yang dapat dihitung sampai Lampiran
 * III (Tunjangan Kinerja) diterima (BPP/137/03/64/2026 §A, §H.1–2).
 * Lihat PayslipComponents untuk daftar yang sengaja masih ditinggalkan,
 * bukan dihilangkan diam-diam.
 *
 * Tunjangan Jabatan/Penyesuaian TIDAK ikut basis iuran pensiun/THT —
 * SK menetapkan dasar iuran itu MURNI Imbalan Kerja (lihat komentar
 * migrasi seed parameter), jadi kedua tunjangan ini HANYA menambah
 * takeHomePartial, bukan basis persentase iuran.
 *
 * Persentase iuran BUKAN konstanta — dibaca pemanggil dari
 * ParameterResolver (CONTRIB_PENSION_EMPLOYEE_PCT, CONTRIB_THT_TOTAL_PCT,
 * CONTRIB_THT_EMPLOYEE_PCT) dan diteruskan ke sini, sejalan dengan pola
 * OVT_xxx dan LEAVE_xxx yang sudah ada.
 */
final readonly class GajiPokokCalculator
{
    public function __construct(
        private float $pensionEmployeePct,
        private float $thtTotalPct,
        private float $thtEmployeePct,
    ) {}

    public function compute(Money $imbalanKerja, Money $tunjanganJabatan, Money $tunjanganPenyesuaian): PayslipComponents
    {
        $iuranPensiunPegawai = $imbalanKerja->percentage($this->pensionEmployeePct);
        $iuranThtPegawai = $imbalanKerja->percentage($this->thtEmployeePct);
        $iuranThtBank = $imbalanKerja->percentage($this->thtTotalPct - $this->thtEmployeePct);

        $takeHomePartial = $imbalanKerja
            ->add($tunjanganJabatan)
            ->add($tunjanganPenyesuaian)
            ->subtract($iuranPensiunPegawai)
            ->subtract($iuranThtPegawai);

        return new PayslipComponents(
            imbalanKerja: $imbalanKerja,
            tunjanganJabatan: $tunjanganJabatan,
            tunjanganPenyesuaian: $tunjanganPenyesuaian,
            iuranPensiunPegawai: $iuranPensiunPegawai,
            iuranThtPegawai: $iuranThtPegawai,
            iuranThtBank: $iuranThtBank,
            takeHomePartial: $takeHomePartial,
        );
    }
}
