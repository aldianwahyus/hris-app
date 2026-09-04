<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\Http\Support\SystemHealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Kesehatan Sistem (Fase 2) — dipanggil terjadwal
 * (`->everyFiveMinutes()`). Menulis log level WARNING per komponen
 * yang gagal — BUKAN alert eksternal (tidak ada akun Sentry/
 * PagerDuty berbayar), tapi setidaknya tercatat untuk digrep tim ops
 * daripada tidak tercatat sama sekali.
 */
final class CheckSystemHealth extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Periksa kesehatan komponen internal (DB/Redis/antrean/storage) dan catat WARNING bila ada yang gagal.';

    public function __construct(private readonly SystemHealthCheck $health)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $checks = $this->health->run();
        $failing = array_filter($checks, fn (array $c) => ! $c['ok']);

        foreach ($failing as $component => $result) {
            Log::warning("Pemeriksaan kesehatan sistem gagal: {$component} — {$result['detail']}");
            $this->warn("{$component}: GAGAL — {$result['detail']}");
        }

        foreach (array_diff_key($checks, $failing) as $component => $result) {
            $this->info("{$component}: OK — {$result['detail']}");
        }

        return $failing === [] ? self::SUCCESS : self::FAILURE;
    }
}
