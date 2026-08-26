<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Attendance\Contracts\AttendanceRepository;
use App\Modules\Attendance\Domain\AttendanceDeviceGateway;
use App\Modules\Attendance\Infrastructure\EloquentAttendanceRepository;
use App\Modules\Attendance\Infrastructure\StubAttendanceDeviceGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Mengikat AttendanceDeviceGateway ke adaptor sementara — ganti binding
 * ini begitu adaptor SDK mesin fingerprint sungguhan tersedia, tanpa
 * menyentuh Application/SyncDeviceAttendance sama sekali.
 */
final class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttendanceDeviceGateway::class, StubAttendanceDeviceGateway::class);
        $this->app->bind(AttendanceRepository::class, EloquentAttendanceRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
