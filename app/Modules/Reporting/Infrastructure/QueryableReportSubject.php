<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure;

use App\Modules\Reporting\Domain\ReportSubject;
use Illuminate\Database\Query\Builder;

/**
 * Perluasan Infrastructure dari ReportSubject (Domain) — menambah
 * pembangunan query SQL sungguhan, sengaja DIPISAH dari kontrak Domain
 * murni supaya Domain tetap bebas framework (ARCH-001).
 */
interface QueryableReportSubject extends ReportSubject
{
    /** Query dasar (join lengkap) — TANPA select kolom (dipilih terpisah oleh GenerateReport). Lingkup kantor diterapkan bila $officeId tidak null. */
    public function query(?string $officeId): Builder;
}
