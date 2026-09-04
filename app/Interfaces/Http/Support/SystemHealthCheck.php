<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Dashboard Kesehatan Sistem — Fase 2 (evaluasi PM/client 2026-09-03).
 * Pengganti self-hosted untuk "monitoring/observability" — TIDAK ada
 * akun Sentry/Datadog/PagerDuty berbayar di lingkungan ini, jadi
 * kelas ini murni memeriksa konektivitas komponen INTERNAL secara
 * langsung. SETIAP pemeriksaan dibungkus try/catch SENDIRI — satu
 * komponen mati (mis. Redis) TIDAK BOLEH membuat pemeriksaan komponen
 * lain ikut gagal atau menjatuhkan seluruh dashboard.
 */
final class SystemHealthCheck
{
    private const LOG_TAIL_BYTES = 200_000;

    /** @return array<string, array{ok: bool, detail: string}> */
    public function run(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];
    }

    /** @return array<int, string> baris ERROR terakhir, terbaru dulu */
    public function recentErrors(int $limit = 20): array
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return [];
        }

        $size = filesize($path);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $offset = max(0, $size - self::LOG_TAIL_BYTES);
        fseek($handle, $offset);
        $chunk = stream_get_contents($handle);
        fclose($handle);

        if ($chunk === false) {
            return [];
        }

        $lines = array_filter(explode("\n", $chunk), fn (string $line) => str_contains($line, '.ERROR'));

        return array_slice(array_reverse($lines), 0, $limit);
    }

    /** @return array{ok: bool, detail: string} */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'detail' => 'Terhubung ('.config('database.default').')'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['ok' => true, 'detail' => 'Terhubung'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function checkQueue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            return ['ok' => true, 'detail' => "{$pending} tertunda, {$failed} gagal"];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function checkStorage(): array
    {
        try {
            Storage::disk('s3')->exists('healthcheck/.probe');

            return ['ok' => true, 'detail' => 'Terhubung (S3/MinIO)'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
