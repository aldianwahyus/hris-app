<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure;

use App\Core\Domain\Money;
use App\Modules\Payroll\Domain\Pph21Golongan;
use App\Modules\Payroll\Domain\TerRateNotFound;
use App\Modules\Payroll\Domain\TerRateRepository;
use App\Shared\Temporal\Domain\AsOfDate;
use Illuminate\Support\Facades\DB;

final class EloquentTerRateRepository implements TerRateRepository
{
    public function ratePercentFor(Pph21Golongan $golongan, int $grossMonthlyCents, AsOfDate $asOf): float
    {
        $rate = DB::table('pay_pph21_ter_rates')
            ->where('golongan', $golongan->value)
            ->where('income_from_cents', '<=', $grossMonthlyCents)
            ->where(function ($query) use ($grossMonthlyCents) {
                $query->whereNull('income_to_cents')
                    ->orWhere('income_to_cents', '>', $grossMonthlyCents);
            })
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->where(function ($query) use ($asOf) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $asOf->toDateString());
            })
            ->value('rate_percent');

        if ($rate === null) {
            throw TerRateNotFound::forIncome($golongan, Money::fromCents($grossMonthlyCents), $asOf);
        }

        return (float) $rate;
    }
}
