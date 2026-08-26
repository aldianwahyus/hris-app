<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

use App\Shared\Temporal\Domain\AsOfDate;

/**
 * Port ke tabel Tarif Efektif Rata-rata (TER) PPh 21 Bulanan (PMK
 * 168/2023) — BERVERSI seperti pay_salary_scale, karena peraturan
 * pajak berubah lewat PMK baru, bukan konstanta.
 */
interface TerRateRepository
{
    /** Persentase tarif (mis. 5.00 untuk 5%) untuk penghasilan bruto bulanan tertentu. */
    public function ratePercentFor(Pph21Golongan $golongan, int $grossMonthlyCents, AsOfDate $asOf): float;
}
