<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Telat = 'telat';
    case Absen = 'absen';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Telat => 'Terlambat',
            self::Absen => 'Tidak Hadir',
        };
    }
}
