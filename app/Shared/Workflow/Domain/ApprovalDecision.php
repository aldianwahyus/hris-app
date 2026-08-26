<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use DateTimeImmutable;

final readonly class ApprovalDecision
{
    public function __construct(
        public string $deciderId,
        public bool $approved,
        public DateTimeImmutable $decidedAt,
        public ?string $note = null,
        public ?string $delegatedFrom = null,
    ) {
    }

    public function isDelegated(): bool
    {
        return $this->delegatedFrom !== null;
    }
}
