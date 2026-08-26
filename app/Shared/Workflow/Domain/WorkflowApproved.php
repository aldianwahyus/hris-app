<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use App\Core\Domain\DomainEvent;

/**
 * Modul lain BERLANGGANAN peristiwa ini alih-alih memanggil Workflow
 * secara langsung — menjaga ketergantungan tetap searah (aturan M-2/M-3).
 *
 * Contoh: modul Lembur mendengar WorkflowApproved untuk menerbitkan
 * instruksi pembayaran.
 */
final readonly class WorkflowApproved extends DomainEvent
{
    public function __construct(
        public string $instanceId,
        public string $documentType,
        public string $documentId,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'workflow.approved';
    }

    public function payload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
        ];
    }
}
