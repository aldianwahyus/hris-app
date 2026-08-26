<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Infrastructure;

use App\Core\Domain\Money;
use App\Modules\Sppd\Domain\JabatanTier;
use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\SppdTariffComponent;
use App\Modules\Sppd\Domain\SppdTariffNotFound;
use App\Modules\Sppd\Domain\SppdTariffRepository;
use App\Modules\Sppd\Domain\TripCategory;
use App\Shared\Temporal\Domain\AsOfDate;
use Illuminate\Support\Facades\DB;

final class EloquentSppdTariffRepository implements SppdTariffRepository
{
    public function amountFor(
        SppdTariffComponent $component,
        TripCategory $category,
        ?JabatanTier $tier,
        ?RadiusBand $radiusBand,
        AsOfDate $asOf,
    ): Money {
        $query = DB::table('pay_sppd_tariffs')
            ->where('component', $component->value)
            ->where('trip_category', $category->value)
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOf->toDateString());
            });

        $tier === null ? $query->whereNull('jabatan_tier') : $query->where('jabatan_tier', $tier->value);
        $radiusBand === null ? $query->whereNull('radius_band') : $query->where('radius_band', $radiusBand->value);

        $cents = $query->value('amount_cents');

        if ($cents === null) {
            throw SppdTariffNotFound::forLookup($component, $category, $tier, $radiusBand, $asOf);
        }

        return Money::fromCents((int) $cents);
    }
}
