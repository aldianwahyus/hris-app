<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

/**
 * Pola sama App\Modules\Attendance\Application\ImportResult — instance
 * TERPISAH (bukan reuse lintas modul), M-1/M-2 (ModuleBoundaryTest)
 * melarang modul lain mengimpor Application/Domain modul ini kecuali
 * lewat Contracts/.
 */
final readonly class ImportResult
{
    /** @param  array<int, string>  $skippedReasons */
    public function __construct(
        public int $imported,
        public int $skipped,
        public array $skippedReasons,
    ) {}
}
