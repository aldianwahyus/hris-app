<?php

declare(strict_types=1);

namespace App\Modules\Izin\Interfaces\Http\Requests;

use App\Modules\Izin\Domain\IzinCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Hanya memvalidasi BENTUK masukan (wajib diisi, format tanggal,
 * lampiran wajib untuk kategori Sakit). Aturan bisnis lain (urutan
 * tanggal, dsb.) ditegakkan di App\Modules\Izin\Application\
 * SubmitIzinRequest, bukan di sini — supaya berlaku pada semua jalur
 * masuk (web ESS maupun API mobile), pola sama SubmitLeaveRequestForm.
 */
final class SubmitIzinRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', new Enum(IzinCategory::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => [
                $this->string('category')->toString() === IzinCategory::Sakit->value ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attachment.required' => 'Lampiran bukti wajib diisi untuk kategori Sakit.',
        ];
    }
}
