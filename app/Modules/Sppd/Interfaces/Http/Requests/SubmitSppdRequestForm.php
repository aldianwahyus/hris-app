<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Interfaces\Http\Requests;

use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\TripCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Hanya memvalidasi BENTUK masukan. TIDAK ADA kolom uang di sini
 * dengan sengaja — seluruh anggaran (Uang Makan/Saku, plafon Hotel/
 * Angkutan/Transportasi) dihitung sistem lewat SppdBudgetCalculator,
 * bukan diserahkan dari form (§ tidak bisa diisi sembarangan).
 * Pemetaan jenjang jabatan ditegakkan di app/Modules/Sppd/Domain.
 */
final class SubmitSppdRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'trip_category' => ['required', new Enum(TripCategory::class)],
            'destination' => ['required', 'string', 'max:200'],
            'purpose' => ['required', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'radius_band' => ['nullable', new Enum(RadiusBand::class)],
        ];
    }
}
