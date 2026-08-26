<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

/**
 * Satu-satunya pintu bagi modul lain untuk mengetahui "siapa yang
 * sedang bertindak" tanpa menyentuh Domain/Infrastructure Access
 * secara langsung (M-1/M-2 — ModuleBoundaryTest).
 */
interface CurrentActor
{
    public function isAuthenticated(): bool;

    public function userId(): ?string;

    public function employeeId(): ?string;

    public function officeId(): ?string;

    /** @return array<int, string> */
    public function roles(): array;

    public function hasRole(string $role): bool;
}
