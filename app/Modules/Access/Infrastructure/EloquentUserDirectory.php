<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure;

use App\Models\User;
use App\Modules\Access\Contracts\UserDirectory;

final class EloquentUserDirectory implements UserDirectory
{
    /** @return array<int, string> */
    public function userIdsWithRole(string $role): array
    {
        return User::role($role)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
}
