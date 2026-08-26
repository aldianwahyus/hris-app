<?php

declare(strict_types=1);

namespace App\Modules\Leave\Domain;

use DomainException;

final class InsufficientLeaveBalance extends DomainException
{
    public static function forShortfall(float $shortfallDays): self
    {
        $formatted = rtrim(rtrim(number_format($shortfallDays, 1, ',', '.'), '0'), ',');

        return new self("Saldo cuti tidak mencukupi — kekurangan {$formatted} hari.");
    }
}
