<?php

declare(strict_types=1);

namespace App\Shared\Configuration\Infrastructure;

use App\Core\Domain\Money;
use App\Shared\Configuration\Domain\ParameterNotFoundException;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Temporal\Domain\AsOfDate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Implementasi pembacaan parameter dari cfg_parameter_values.
 *
 * Hasil di-cache per (kode, tanggal) karena parameter jarang berubah
 * namun sering dibaca — mis. tarif lembur dipanggil untuk setiap baris
 * perhitungan. Cache dikunci pada tanggal acuan sehingga perhitungan
 * historis tidak tercemar nilai terkini.
 */
final class DatabaseParameterResolver implements ParameterResolver
{
    private const CACHE_TTL_SECONDS = 3600;

    public function integer(string $code, AsOfDate $asOf): int
    {
        return (int) $this->rawValue($code, $asOf);
    }

    public function decimal(string $code, AsOfDate $asOf): float
    {
        return (float) $this->rawValue($code, $asOf);
    }

    public function money(string $code, AsOfDate $asOf): Money
    {
        return Money::fromRupiah($this->rawValue($code, $asOf));
    }

    public function string(string $code, AsOfDate $asOf): string
    {
        return $this->rawValue($code, $asOf);
    }

    public function boolean(string $code, AsOfDate $asOf): bool
    {
        return filter_var($this->rawValue($code, $asOf), FILTER_VALIDATE_BOOLEAN);
    }

    public function sourceDocument(string $code, AsOfDate $asOf): ?string
    {
        return $this->row($code, $asOf)->source_document ?? null;
    }

    private function rawValue(string $code, AsOfDate $asOf): string
    {
        return $this->row($code, $asOf)->value;
    }

    private function row(string $code, AsOfDate $asOf): object
    {
        $cacheKey = sprintf('cfg:%s:%s', $code, $asOf->toDateString());

        $row = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($code, $asOf) {
            return DB::table('cfg_parameter_values as v')
                ->join('cfg_parameters as p', 'p.id', '=', 'v.parameter_id')
                ->where('p.code', $code)
                ->whereNull('v.deleted_at')
                ->whereDate('v.effective_from', '<=', $asOf->toDateString())
                ->where(function ($query) use ($asOf) {
                    $query->whereNull('v.effective_to')
                        ->orWhereDate('v.effective_to', '>=', $asOf->toDateString());
                })
                ->select('v.value', 'v.source_document')
                ->first();
        });

        if ($row === null) {
            throw ParameterNotFoundException::forCode($code, $asOf);
        }

        return $row;
    }
}
