<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

use App\Core\Domain\Money;

/**
 * Komponen slip gaji — SENGAJA MASIH TIDAK LENGKAP.
 *
 * BPP/137/03/64/2026 menetapkan Tunjangan Jabatan dan Tunjangan Kinerja
 * pada Lampiran III. Tunjangan Jabatan SUDAH tersedia (SEC-2026-08-TJ
 * — field editable pada data induk pegawai, TIDAK punya tabel/kriteria
 * baku sehingga diisi manual per pegawai lewat maker-checker, lihat
 * EditableEmployeeField). Tunjangan Kinerja MASIH belum. Karena
 * Tunjangan Kemahalan dihitung dari (Jabatan+Kinerja) dan Iuran Astek
 * dari GAJI KOTOR (yang mencakup keduanya), seluruh turunannya ikut
 * masih belum dapat dihitung — bukan diasumsikan nol, melainkan TIDAK
 * DIKETAHUI.
 *
 * `takeHomePartial` BUKAN gaji bersih sesungguhnya — Imbalan Kerja +
 * Tunjangan Jabatan + Tunjangan Penyesuaian, dikurangi iuran yang
 * MURNI berbasis Imbalan Kerja saja (BUKAN basis gabungan — lihat
 * GajiPokokCalculator, dasar iuran TIDAK berubah). Layar wajib
 * menampilkan `pendingComponents()` berdampingan, tidak pernah
 * menyembunyikannya.
 */
final readonly class PayslipComponents
{
    public function __construct(
        public Money $imbalanKerja,
        public Money $tunjanganJabatan,
        public Money $tunjanganPenyesuaian,
        public Money $iuranPensiunPegawai,
        public Money $iuranThtPegawai,
        public Money $iuranThtBank,
        public Money $takeHomePartial,
    ) {}

    /** Iuran pensiun bagian Bank bersifat aktuaria (BPP §H.1) — tidak pernah dihitung di sini. */
    public function iuranPensiunBankDiketahui(): bool
    {
        return false;
    }

    /**
     * $pph21Computed: true bila PPh 21 SEMENTARA sudah dihitung untuk
     * pegawai ini (lihat RunPayrollDraft) — basisnya MASIH belum
     * mencakup Tunjangan Kinerja/Kemahalan, jadi tetap wajib disertai
     * peringatan provisional, BUKAN dianggap final begitu ada angkanya.
     * false: PTKP pegawai (status kawin/tanggungan) belum lengkap di
     * data induk, sehingga golongan TER tidak bisa ditentukan sama
     * sekali — PPh21 belum dihitung untuk pegawai ini.
     *
     * @return array<int, string>
     */
    public function pendingComponents(bool $pph21Computed = false): array
    {
        return [
            'Tunjangan Kinerja (menunggu Lampiran III)',
            'Tunjangan Kemahalan (bergantung Jabatan+Kinerja — Kinerja belum tersedia)',
            'Iuran Astek/BPJS Ketenagakerjaan (berbasis Gaji Kotor, bergantung Kinerja+Kemahalan)',
            'Iuran Pensiun bagian Bank (aktuaria, bukan persentase tetap)',
            ...($pph21Computed
                ? ['PPh 21 SEMENTARA (provisional) — dihitung dari Gaji Kotor yang BELUM mencakup Tunjangan Kinerja/Kemahalan. WAJIB dikoreksi ulang setelah Lampiran III lengkap, jangan dianggap final.']
                : ['PPh 21 belum dihitung untuk pegawai ini — data PTKP (status kawin/jumlah tanggungan) belum lengkap pada data induk pegawai.']),
        ];
    }
}
