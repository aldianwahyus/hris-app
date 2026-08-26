<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

use DomainException;

final class DailyOvertimeLimitExceeded extends DomainException
{
    public static function forRequest(float $requestedHours, float $capHours): self
    {
        $req = rtrim(rtrim(number_format($requestedHours, 1, ',', '.'), '0'), ',');
        $cap = rtrim(rtrim(number_format($capHours, 1, ',', '.'), '0'), ',');

        return new self("Pengajuan {$req} jam melampaui batas {$cap} jam per hari untuk lembur biasa.");
    }
}
