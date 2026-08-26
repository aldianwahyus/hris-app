<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

use DomainException;

final class SegregationOfDutyViolation extends DomainException
{
    public static function forRole(Role $role): self
    {
        return new self(sprintf(
            'Peran "%s" tidak dapat diberikan — melanggar pemisahan tugas (ARCH-001 §6.3).',
            $role->label()
        ));
    }
}
