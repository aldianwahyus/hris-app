<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

final readonly class BulkPayrollDecisionResult
{
    /** @param  array<int, string>  $approvedOfficeNames */
    public function __construct(
        public array $approvedOfficeNames,
        public string $period,
    ) {}
}
