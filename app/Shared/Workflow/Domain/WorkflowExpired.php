<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use App\Core\Domain\DomainEvent;

/**
 * Modul Lembur mendengar peristiwa ini untuk melepas kuota mingguan
 * sekaligus mencatat bahwa hak bayar pegawai hangus.
 */
final readonly class WorkflowExpired extends DomainEvent
{
    public function __construct(
        public string $instanceId,
        public string $documentType,
        public string $documentId,
        public string $reason,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'workflow.expired';
    }

    public function payload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'reason' => $this->reason,
        ];
    }
}
