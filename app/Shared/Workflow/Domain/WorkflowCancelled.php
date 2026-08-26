<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use App\Core\Domain\DomainEvent;

final readonly class WorkflowCancelled extends DomainEvent
{
    public function __construct(
        public string $instanceId,
        public string $documentType,
        public string $documentId,
        public string $cancelledBy,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'workflow.cancelled';
    }

    public function payload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'cancelled_by' => $this->cancelledBy,
        ];
    }
}
