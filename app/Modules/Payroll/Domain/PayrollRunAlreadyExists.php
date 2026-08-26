<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

use DomainException;

final class PayrollRunAlreadyExists extends DomainException
{
    public static function forPeriod(string $period): self
    {
        return new self("Payroll run untuk kantor dan periode {$period} sudah pernah dibuat.");
    }
}
