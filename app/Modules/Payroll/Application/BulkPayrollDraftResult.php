<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

final readonly class BulkPayrollDraftResult
{
    /**
     * @param  array<int, string>  $createdOfficeNames
     * @param  array<int, string>  $skippedAlreadyExistsOfficeNames
     */
    public function __construct(
        public array $createdOfficeNames,
        public array $skippedAlreadyExistsOfficeNames,
    ) {}
}
