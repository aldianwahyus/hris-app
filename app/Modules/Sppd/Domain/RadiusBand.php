<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

/** Pita jarak tempuh untuk uang makan Jarak Pendek (BPP §III.A.1). Radius <30km TIDAK diberikan uang makan sama sekali — bukan pilihan di sini. */
enum RadiusBand: string
{
    case Km30To100 = '30_100';
    case Km100To150 = '100_150';
    case KmAbove150 = '150_plus';

    public function label(): string
    {
        return match ($this) {
            self::Km30To100 => '30 km – 100 km',
            self::Km100To150 => '>100 km – 150 km',
            self::KmAbove150 => '>150 km',
        };
    }
}
