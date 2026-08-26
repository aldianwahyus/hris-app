<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

final readonly class SalaryChangeImportResult
{
    /** @param  array<int, string>  $skippedReasons */
    public function __construct(
        public int $imported,
        public int $skippedNoChange,
        public int $skipped,
        public array $skippedReasons,
    ) {}
}
