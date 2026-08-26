<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Audit\Infrastructure\EloquentAuditRepository;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Configuration\Infrastructure\DatabaseParameterResolver;
use App\Shared\Holiday\Domain\HolidayRepository;
use App\Shared\Holiday\Infrastructure\EloquentHolidayRepository;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use App\Shared\Workflow\Infrastructure\EloquentWorkflowInstanceRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Mengikat kontrak Domain ke implementasi Infrastructure.
 *
 * Inilah titik di mana Clean Architecture menjadi nyata: Domain hanya
 * mengenal interface, dan pemilihan implementasi terjadi di sini —
 * di luar Domain. Mengganti PostgreSQL dengan penyimpanan lain cukup
 * mengubah baris di berkas ini, tanpa menyentuh satu pun aturan bisnis.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditRepository::class, EloquentAuditRepository::class);
        $this->app->singleton(ParameterResolver::class, DatabaseParameterResolver::class);
        $this->app->singleton(HolidayRepository::class, EloquentHolidayRepository::class);
        $this->app->bind(WorkflowInstanceRepository::class, EloquentWorkflowInstanceRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
