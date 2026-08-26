<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

final readonly class PayslipDeductionImportResult
{
    /** @param  array<int, string>  $skippedReasons */
    public function __construct(
        public int $imported,
        public int $skipped,
        public array $skippedReasons,
    ) {}
}
