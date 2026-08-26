<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

/**
 * Golongan Tarif Efektif Rata-rata (TER) PPh 21 Bulanan — PMK 168/2023.
 *
 * Ditentukan dari status PTKP (kawin + jumlah tanggungan, maksimal 3):
 *   Golongan A: TK/0, TK/1, K/0
 *   Golongan B: TK/2, K/1, TK/3, K/2
 *   Golongan C: K/3
 */
enum Pph21Golongan: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public static function fromStatus(bool $married, int $dependents): self
    {
        $dependents = max(0, min(3, $dependents));

        if (! $married) {
            return $dependents <= 1 ? self::A : self::B; // TK/0,TK/1 | TK/2,TK/3
        }

        return match ($dependents) {
            0 => self::A,       // K/0
            3 => self::C,       // K/3
            default => self::B, // K/1, K/2
        };
    }

    public function ptkpLabel(bool $married, int $dependents): string
    {
        $dependents = max(0, min(3, $dependents));
        $prefix = $married ? 'K' : 'TK';

        return "{$prefix}/{$dependents}";
    }
}
