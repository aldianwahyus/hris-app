<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use App\Core\Domain\DomainEvent;

final readonly class WorkflowStepAdvanced extends DomainEvent
{
    public function __construct(
        public string $instanceId,
        public string $documentType,
        public string $documentId,
        public int $newSequence,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'workflow.step_advanced';
    }

    public function payload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'new_sequence' => $this->newSequence,
        ];
    }
}
