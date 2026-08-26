<?php

declare(strict_types=1);

namespace App\Modules\Employee\Domain;

use DomainException;

/**
 * Data pegawai sudah berubah sejak pengajuan dibuat (optimistic lock
 * pada emp_employees.version) — mis. pengajuan lain sudah lebih dulu
 * disetujui. Penyetuju perlu meninjau ulang data terkini sebelum
 * memutuskan lagi, bukan menimpa perubahan yang sudah terjadi.
 */
final class ProfileChangeConflict extends DomainException
{
    public static function forEmployee(string $employeeId): self
    {
        return new self(sprintf(
            'Data pegawai (id: %s) sudah berubah sejak pengajuan ini dibuat. Tinjau ulang data terkini sebelum memutuskan.',
            $employeeId,
        ));
    }
}
