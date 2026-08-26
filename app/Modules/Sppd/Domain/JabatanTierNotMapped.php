<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

use DomainException;

final class JabatanTierNotMapped extends DomainException
{
    public static function forEmployee(string $employeeId): self
    {
        return new self(
            'Jabatan pegawai ini belum dipetakan ke jenjang tarif SPPD (md_positions.sppd_jabatan_tier). '
            .'Hubungi Admin SDM/Admin Sistem untuk melengkapi pemetaan sebelum mengajukan SPPD.'
        );
    }
}
