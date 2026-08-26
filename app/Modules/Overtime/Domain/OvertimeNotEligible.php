<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

use DomainException;

final class OvertimeNotEligible extends DomainException
{
    public static function forType(OvertimeType $type): self
    {
        return new self(
            "Jabatan pegawai ini tidak berhak mengajukan {$type->label()} "
            .'(KEP/1827/06/64/2026 Pasal 6.1).'
        );
    }
}
