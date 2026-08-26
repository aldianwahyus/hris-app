<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

/**
 * Menggabungkan ketiga sumbu ARCH-001 §6.2: Role × Organizational
 * Scope × Ownership. Sebuah rekaman dapat diakses bila aktor
 * PEMILIKnya, ATAU rekaman berada dalam lingkup organisasi perannya.
 */
final readonly class AccessPolicy
{
    public function __construct(
        public Role $role,
        public OrganizationalScope $scope,
    ) {}

    public function canAccessRecord(
        ?string $recordOfficeId,
        ?string $recordOwnerEmployeeId,
        ?string $actingEmployeeId,
    ): bool {
        if ($recordOwnerEmployeeId !== null && $actingEmployeeId !== null
            && Ownership::isOwnedBy($recordOwnerEmployeeId, $actingEmployeeId)) {
            return true;
        }

        if ($recordOfficeId === null) {
            return false;
        }

        return $this->scope->coversOffice($recordOfficeId);
    }

    /** Persetujuan tidak boleh dilakukan atas pengajuan milik sendiri (§6.3). */
    public function canApprove(?string $recordOwnerEmployeeId, ?string $actingEmployeeId): bool
    {
        if ($this->role->isReadOnly()) {
            return false;
        }

        if ($recordOwnerEmployeeId !== null && $actingEmployeeId !== null
            && Ownership::isOwnedBy($recordOwnerEmployeeId, $actingEmployeeId)) {
            return false;
        }

        return true;
    }
}
