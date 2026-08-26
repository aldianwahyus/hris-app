<?php

declare(strict_types=1);

namespace App\Shared\Audit\Domain;

/**
 * Pelaku tindakan beserta konteks teknisnya.
 *
 * actorId boleh null HANYA untuk proses sistem (scheduler, job).
 * Peran direkam APA ADANYA saat tindakan terjadi — bukan dirujuk ke
 * tabel peran saat laporan dibuat. Alasannya: peran pegawai berubah
 * seiring waktu, dan auditor perlu tahu kapasitas apa yang dipakai
 * seseorang SAAT itu, bukan jabatannya sekarang.
 */
final readonly class AuditActor
{
    public function __construct(
        public ?string $actorId,
        public ?string $actorRole = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }

    public static function system(string $processName): self
    {
        return new self(null, "system:{$processName}");
    }

    public function isSystem(): bool
    {
        return $this->actorId === null;
    }
}
