<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure;

use App\Core\Domain\Money;
use App\Modules\Payroll\Domain\SalaryScaleRepository;
use App\Modules\Payroll\Domain\SalaryStepNotFound;
use App\Shared\Temporal\Domain\AsOfDate;
use Illuminate\Support\Facades\DB;

final class EloquentSalaryScaleRepository implements SalaryScaleRepository
{
    public function amountFor(int $personGrade, int $step, AsOfDate $asOf): Money
    {
        $cents = DB::table('pay_salary_scale')
            ->where('person_grade', $personGrade)
            ->where('step', $step)
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->where(function ($query) use ($asOf) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $asOf->toDateString());
            })
            ->value('amount_cents');

        if ($cents === null) {
            throw SalaryStepNotFound::forStep($personGrade, $step, $asOf);
        }

        return Money::fromCents((int) $cents);
    }
}
