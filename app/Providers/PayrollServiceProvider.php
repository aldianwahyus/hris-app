<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Payroll\Domain\SalaryScaleRepository;
use App\Modules\Payroll\Domain\TerRateRepository;
use App\Modules\Payroll\Infrastructure\EloquentSalaryScaleRepository;
use App\Modules\Payroll\Infrastructure\EloquentTerRateRepository;
use Illuminate\Support\ServiceProvider;

final class PayrollServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SalaryScaleRepository::class, EloquentSalaryScaleRepository::class);
        $this->app->bind(TerRateRepository::class, EloquentTerRateRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
