<?php

declare(strict_types=1);

namespace App\Shared\Temporal\Domain;

use DateTimeImmutable;

/**
 * Tanggal acuan untuk pembacaan entitas temporal.
 *
 * ATURAN YANG DITEGAKKAN (ARCH-001 §5.1): setiap pembacaan entitas temporal
 * WAJIB menyertakan tanggal acuan. Dengan menjadikannya tipe tersendiri
 * (bukan sekadar ?DateTimeImmutable opsional), kelalaian tertangkap saat
 * analisis statis — bukan saat audit.
 *
 *   ✅ $repo->findPlacement($employeeId, AsOfDate::today());
 *   ✅ $repo->findPlacement($employeeId, AsOfDate::on($tanggalPengajuan));
 *   ❌ $repo->findPlacement($employeeId);   // tidak dapat dikompilasi
 */
final readonly class AsOfDate
{
    private function __construct(public DateTimeImmutable $date)
    {
    }

    public static function today(): self
    {
        return new self(new DateTimeImmutable('today'));
    }

    public static function on(DateTimeImmutable $date): self
    {
        return new self($date);
    }

    public function toDateString(): string
    {
        return $this->date->format('Y-m-d');
    }
}
