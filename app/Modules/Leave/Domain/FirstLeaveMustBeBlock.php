<?php

declare(strict_types=1);

namespace App\Modules\Leave\Domain;

use DomainException;

final class FirstLeaveMustBeBlock extends DomainException
{
    public static function of(float $minimumDays, float $requestedDays): self
    {
        $min = rtrim(rtrim(number_format($minimumDays, 1, ',', '.'), '0'), ',');
        $requested = rtrim(rtrim(number_format($requestedDays, 1, ',', '.'), '0'), ',');

        return new self(
            'Pengambilan cuti tahunan pertama pada tahun berjalan wajib sekaligus '
            ."minimal {$min} hari — pengajuan ini hanya {$requested} hari."
        );
    }
}
