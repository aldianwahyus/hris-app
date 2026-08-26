<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

use App\Core\Domain\Money;
use App\Shared\Temporal\Domain\AsOfDate;

/**
 * Port ke Tabel Skala Imbalan Kerja (Lampiran II, BPP/137/03/64/2026)
 * — matriks person_grade × baris, BERVERSI seperti seluruh parameter
 * kebijakan lain, karena tabel ini turut berubah lewat SK Direksi.
 *
 * Bukan cfg_parameters biasa: nilainya berbentuk matriks (665 sel),
 * bukan skalar tunggal per kode — karena itu disimpan di tabelnya
 * sendiri (pay_salary_scale), bukan dipaksakan ke cfg_parameter_values.
 */
interface SalaryScaleRepository
{
    /** @throws SalaryStepNotFound bila kombinasi person_grade+baris belum ada datanya pada tanggal acuan. */
    public function amountFor(int $personGrade, int $step, AsOfDate $asOf): Money;
}
