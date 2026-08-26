<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Application;

/** Satu ambang pengingat (H-7/H-3) yang baru saja jatuh tempo untuk dikirim. */
final readonly class ReminderDue
{
    public function __construct(
        public string $documentType,
        public string $documentId,
        public string $instanceId,
        public int $thresholdDays,
        public string $requestNumber,
    ) {}
}
