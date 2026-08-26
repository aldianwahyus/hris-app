<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application;

final readonly class ImportResult
{
    /** @param  array<int, string>  $skippedReasons */
    public function __construct(
        public int $imported,
        public int $skipped,
        public array $skippedReasons,
    ) {}
}
