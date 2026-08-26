<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

/**
 * Satu-satunya pintu bagi modul lain untuk menemukan akun berdasarkan
 * peran, tanpa menyentuh Eloquent/Spatie milik Access secara langsung.
 */
interface UserDirectory
{
    /** @return array<int, string> id akun (users.id) yang memegang peran ini */
    public function userIdsWithRole(string $role): array;
}
