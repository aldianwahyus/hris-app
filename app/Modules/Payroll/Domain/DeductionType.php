<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

/**
 * Kategori potongan gaji tambahan, diinput admin cabang (maker) selama
 * payroll run masih draft.
 *
 * CATATAN JUJUR: DendaPelanggaran di sini murni kategori input MANUAL
 * — TIDAK terhubung otomatis ke modul Pelanggaran/KPP. Integrasi
 * otomatis (mis. denda dari keputusan komite langsung masuk sini)
 * belum diminta/dikerjakan.
 */
enum DeductionType: string
{
    case KasbonPinjaman = 'kasbon_pinjaman';
    case DendaPelanggaran = 'denda_pelanggaran';
    case Lainnya = 'lainnya';

    // Fase II (format KITIR) — Astek input manual SEMENTARA (formula
    // resmi berbasis Gaji Kotor+Tunjangan Kinerja belum tersedia, lihat
    // PayslipComponents::pendingComponents()). Sisanya kategori potongan
    // pihak ketiga tetap (asuransi, koperasi, bank, dll.) yang memang
    // sudah lazim dipotong lewat payroll bank.
    case Astek = 'astek';
    case Asuransi = 'asuransi';
    case Bazis = 'bazis';
    case Iuran = 'iuran';
    case Arisan = 'arisan';
    case Koperasi = 'koperasi';
    case Kesra = 'kesra';
    case Bank = 'bank';
    case Lkp = 'lkp';
    case Bpr = 'bpr';

    public function label(): string
    {
        return match ($this) {
            self::KasbonPinjaman => 'Kasbon/Pinjaman',
            self::DendaPelanggaran => 'Denda/Pelanggaran',
            self::Lainnya => 'Lainnya',
            self::Astek => 'Astek/BPJS Ketenagakerjaan',
            self::Asuransi => 'Asuransi',
            self::Bazis => 'Bazis',
            self::Iuran => 'Iuran',
            self::Arisan => 'Arisan',
            self::Koperasi => 'Koperasi',
            self::Kesra => 'Kesra',
            self::Bank => 'Bank',
            self::Lkp => 'LKP',
            self::Bpr => 'BPR',
        };
    }
}
