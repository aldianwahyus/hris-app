<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

use App\Shared\Temporal\Domain\AsOfDate;
use DomainException;

final class SppdTariffNotFound extends DomainException
{
    public static function forLookup(
        SppdTariffComponent $component,
        TripCategory $category,
        ?JabatanTier $tier,
        ?RadiusBand $radiusBand,
        AsOfDate $asOf,
    ): self {
        return new self(sprintf(
            'Tarif SPPD %s untuk %s%s%s tidak ditemukan per %s.',
            $component->value,
            $category->value,
            $tier !== null ? " jenjang {$tier->value}" : '',
            $radiusBand !== null ? " pita {$radiusBand->value}" : '',
            $asOf->toDateString(),
        ));
    }
}
