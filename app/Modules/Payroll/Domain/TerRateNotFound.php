<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

use App\Core\Domain\Money;
use App\Shared\Temporal\Domain\AsOfDate;
use DomainException;

final class TerRateNotFound extends DomainException
{
    public static function forIncome(Pph21Golongan $golongan, Money $grossMonthly, AsOfDate $asOf): self
    {
        return new self(sprintf(
            'Tarif TER Golongan %s untuk penghasilan bruto %s tidak ditemukan per %s.',
            $golongan->value,
            $grossMonthly->format(),
            $asOf->toDateString(),
        ));
    }
}
