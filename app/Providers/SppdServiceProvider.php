<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Sppd\Domain\SppdTariffRepository;
use App\Modules\Sppd\Infrastructure\EloquentSppdTariffRepository;
use Illuminate\Support\ServiceProvider;

final class SppdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SppdTariffRepository::class, EloquentSppdTariffRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
