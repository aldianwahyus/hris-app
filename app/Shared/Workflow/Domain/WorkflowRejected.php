<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use App\Core\Domain\DomainEvent;

final readonly class WorkflowRejected extends DomainEvent
{
    public function __construct(
        public string $instanceId,
        public string $documentType,
        public string $documentId,
        public string $rejectedBy,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'workflow.rejected';
    }

    public function payload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'rejected_by' => $this->rejectedBy,
        ];
    }
}
