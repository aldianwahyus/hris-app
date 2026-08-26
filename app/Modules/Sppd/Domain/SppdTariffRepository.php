<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

use App\Core\Domain\Money;
use App\Shared\Temporal\Domain\AsOfDate;

/**
 * Port ke tabel tarif SPPD (BPP/442/03/64/2026), BERVERSI seperti
 * pay_salary_scale/pay_pph21_ter_rates — matriks komponen × kategori
 * perjalanan × jenjang jabatan/pita radius, bukan skalar tunggal.
 */
interface SppdTariffRepository
{
    /** @throws SppdTariffNotFound */
    public function amountFor(
        SppdTariffComponent $component,
        TripCategory $category,
        ?JabatanTier $tier,
        ?RadiusBand $radiusBand,
        AsOfDate $asOf,
    ): Money;
}
