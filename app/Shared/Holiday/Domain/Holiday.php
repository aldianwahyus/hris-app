<?php

declare(strict_types=1);

namespace App\Shared\Holiday\Domain;

use DateTimeImmutable;

final readonly class Holiday
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $date,
        public string $name,
        public bool $isNational,
    ) {}
}
