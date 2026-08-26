<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Contracts\UserDirectory;
use App\Modules\Access\Infrastructure\EloquentCurrentActor;
use App\Modules\Access\Infrastructure\EloquentUserDirectory;
use App\Modules\Employee\Contracts\EmployeeRepository;
use App\Modules\Employee\Infrastructure\EloquentEmployeeRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Mengikat kontrak Domain ke implementasi Infrastructure untuk modul
 * Employee dan Access — pola yang sama dengan SharedServiceProvider.
 */
final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmployeeRepository::class, EloquentEmployeeRepository::class);
        $this->app->bind(CurrentActor::class, EloquentCurrentActor::class);
        $this->app->bind(UserDirectory::class, EloquentUserDirectory::class);
    }

    public function boot(): void
    {
        //
    }
}
