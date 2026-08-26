<?php

declare(strict_types=1);

namespace App\Modules\Leave\Domain;

/**
 * Sembilan jenis cuti sesuai skema (DB-001) / BPP Fasilitas Cuti.
 *
 * Tahap 2 hanya mengimplementasikan aturan bisnis penuh untuk CT (Cuti
 * Tahunan) — jenis lain (CB/CS/CBS/CGK/CAP/CIB/CLT/CBR) membutuhkan
 * kelayakan tersendiri (mis. CB: 6 tahun sejak pengangkatan tetap; CS:
 * verifikasi surat dokter) yang datanya belum tersedia di Wave 1.
 */
enum LeaveType: string
{
    case CutiTahunan = 'CT';
    case CutiBesar = 'CB';
    case CutiSakit = 'CS';
    case CutiBersalin = 'CBS';
    case CutiGugurKandungan = 'CGK';
    case CutiAlasanPenting = 'CAP';
    case CutiIbadah = 'CIB';
    case CutiLuarTanggungan = 'CLT';
    case CutiBersama = 'CBR';

    public function label(): string
    {
        return match ($this) {
            self::CutiTahunan => 'Cuti Tahunan',
            self::CutiBesar => 'Cuti Besar',
            self::CutiSakit => 'Cuti Sakit',
            self::CutiBersalin => 'Cuti Bersalin',
            self::CutiGugurKandungan => 'Cuti Gugur Kandungan',
            self::CutiAlasanPenting => 'Cuti Alasan Penting',
            self::CutiIbadah => 'Cuti Ibadah',
            self::CutiLuarTanggungan => 'Cuti di Luar Tanggungan',
            self::CutiBersama => 'Cuti Bersama',
        };
    }

    /**
     * Hanya Cuti Tahunan yang mengonsumsi kantong saldo (current_year /
     * carry_forward) pada Tahap 2. Jenis lain memakai jalur kelayakan
     * berbeda yang belum dibangun.
     */
    public function consumesAnnualBalance(): bool
    {
        return $this === self::CutiTahunan;
    }
}
