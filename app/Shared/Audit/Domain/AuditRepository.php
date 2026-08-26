<?php

declare(strict_types=1);

namespace App\Shared\Audit\Domain;

use DateTimeImmutable;

/**
 * Kontrak penyimpanan audit — TANPA metode ubah atau hapus.
 *
 * Ketiadaan update()/delete() di sini bukan kelalaian, melainkan
 * penegakan sifat append-only pada tingkat tipe. Kode yang mencoba
 * mengubah entri audit tidak akan menemukan cara melakukannya.
 */
interface AuditRepository
{
    public function append(AuditEntry $entry): void;

    /** @param array<int, AuditEntry> $entries */
    public function appendMany(array $entries): void;

    /** @return array<int, AuditEntry> */
    public function findForAuditable(string $auditableType, string $auditableId): array;

    /** @return array<int, AuditEntry> */
    public function findByActor(string $actorId, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
