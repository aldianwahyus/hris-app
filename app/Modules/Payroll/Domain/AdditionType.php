<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

/**
 * Kategori tambahan penghasilan ad-hoc, diinput admin cabang (maker)
 * selama payroll run masih draft — cermin DeductionType tapi menambah
 * THP, bukan mengurangi. Sengaja satu case saat ini (Tunjangan
 * Pengobatan, format KITIR) — enum ini yang membuat kategori baru
 * nanti cukup tambah case, tanpa migrasi (pola sama DeductionType).
 */
enum AdditionType: string
{
    case TunjanganPengobatan = 'tunjangan_pengobatan';

    public function label(): string
    {
        return match ($this) {
            self::TunjanganPengobatan => 'Tunjangan Pengobatan',
        };
    }
}
