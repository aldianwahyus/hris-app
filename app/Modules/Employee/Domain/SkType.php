<?php

declare(strict_types=1);

namespace App\Modules\Employee\Domain;

/**
 * Jenis Surat Keputusan (SK) — diinput SYSADMIN/hr_admin lewat modul
 * SK. Enum ini murni pemetaan label; logika "tipe mana yang memicu
 * pengajuan perubahan data induk & field apa saja" ditegakkan di
 * Application/Interfaces (pola sama DeductionType/AdditionType/
 * AttendanceSource — enum tidak menyimpan aturan bisnis).
 */
enum SkType: string
{
    case Mutasi = 'mutasi';
    case Promosi = 'promosi';
    case Sanksi = 'sanksi';
    case PerubahanGaji = 'perubahan_gaji';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Mutasi => 'Mutasi',
            self::Promosi => 'Promosi',
            self::Sanksi => 'Sanksi',
            self::PerubahanGaji => 'Perubahan Gaji',
            self::Lainnya => 'Lainnya',
        };
    }
}
