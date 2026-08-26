<?php

declare(strict_types=1);

namespace App\Modules\Leave\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hanya memvalidasi BENTUK masukan (wajib diisi, format tanggal,
 * urutan tanggal). Aturan bisnis (urutan konsumsi kantong, blok
 * minimal pengambilan pertama) ditegakkan di app/Modules/Leave/Domain,
 * bukan di sini — supaya berlaku pada semua jalur masuk, tidak hanya
 * formulir web ini.
 */
final class SubmitLeaveRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
