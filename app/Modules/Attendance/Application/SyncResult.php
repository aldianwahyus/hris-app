<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application;

final readonly class SyncResult
{
    public function __construct(
        public int $matched,
        public int $unmatched,
    ) {}
}
