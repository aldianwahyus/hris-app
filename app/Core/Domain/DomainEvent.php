<?php

declare(strict_types=1);

namespace App\Core\Domain;

use DateTimeImmutable;

/**
 * Domain Event — sarana komunikasi antar modul (ARCH-001 aturan M-2).
 *
 * Modul TIDAK boleh memanggil modul lain secara langsung. Komunikasi hanya
 * melalui kontrak publik (Contracts/) atau Domain Event seperti ini.
 * Dengan demikian modul tetap dapat dipisahkan menjadi service mandiri
 * di masa depan tanpa refactor domain (NFR-12).
 */
abstract readonly class DomainEvent
{
    public string $eventId;

    public DateTimeImmutable $occurredAt;

    public function __construct(?DateTimeImmutable $occurredAt = null)
    {
        $this->eventId = (string) Uuid7::generate();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    /** Nama event untuk pencatatan & routing, mis. "overtime.request.approved". */
    abstract public function eventName(): string;

    /** Payload yang akan diserialisasi ke audit trail / message bus. */
    abstract public function payload(): array;
}
