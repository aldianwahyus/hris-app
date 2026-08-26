<?php

declare(strict_types=1);

namespace App\Core\Domain;

/**
 * Aggregate Root — batas konsistensi transaksional dalam Domain.
 *
 * Aturan bisnis (invariant) ditegakkan DI SINI, bukan di Controller
 * maupun Form Request. Alasannya konkret: plafon lembur 18 jam/minggu
 * bersifat penolakan keras (DEC-31). Bila diletakkan di Form Request,
 * aturan itu dapat ditembus lewat pemanggilan API langsung, perintah
 * artisan, atau proses impor data. Diletakkan di Domain, aturan berlaku
 * pada SEMUA jalur masuk tanpa terkecuali — dan inilah yang akan diuji
 * auditor.
 */
abstract class AggregateRoot
{
    /** @var array<int, DomainEvent> */
    private array $recordedEvents = [];

    protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return array<int, DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
