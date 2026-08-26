<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Application;

/** Satu instansi yang baru saja dinyatakan kedaluwarsa karena tenggat SLA terlewati. */
final readonly class InstanceExpired
{
    public function __construct(
        public string $documentType,
        public string $documentId,
        public string $instanceId,
        public string $requestNumber,
    ) {}
}
