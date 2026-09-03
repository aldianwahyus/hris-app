<?php

declare(strict_types=1);

namespace App\Modules\Employee\Domain;

/**
 * Pegawai — potret data yang dibutuhkan modul lain untuk memutuskan
 * akses (NRP, kantor, jabatan). BUKAN salinan penuh `emp_employees`;
 * modul yang butuh field lain menambah properti di sini, bukan
 * mengakses tabel secara langsung (M-1/M-2 — ModuleBoundaryTest).
 */
final readonly class Employee
{
    public function __construct(
        public string $id,
        public string $nrp,
        public string $fullName,
        public string $officeId,
        public string $positionId,
        public EmploymentStatus $employmentStatus,
        public ?string $separatedAt = null,
    ) {}

    /** Offboarding — akun pegawai yang sudah keluar tidak boleh lagi login (lihat AuthenticateEmployee). */
    public function isSeparated(): bool
    {
        return $this->separatedAt !== null;
    }
}
