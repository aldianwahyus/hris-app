<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure;

use App\Models\User;
use App\Modules\Access\Contracts\CurrentActor;
use Illuminate\Support\Facades\Auth;

final class EloquentCurrentActor implements CurrentActor
{
    public function isAuthenticated(): bool
    {
        return Auth::check();
    }

    public function userId(): ?string
    {
        $user = Auth::user();

        return $user === null ? null : (string) $user->getKey();
    }

    public function employeeId(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->employee_id;
    }

    public function officeId(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->employee?->office_id;
    }

    /** @return array<int, string> */
    public function roles(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->getRoleNames()->all() ?? [];
    }

    public function hasRole(string $role): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole($role) ?? false;
    }

    public function hasPermission(string $permission): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo($permission) ?? false;
    }
}
