<?php

declare(strict_types=1);

namespace App\Modules\Shift\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hanya memvalidasi BENTUK masukan. Aturan bisnis (tidak boleh tukar
 * dengan diri sendiri, tanggal tidak boleh masa lalu, kedua pihak wajib
 * punya penugasan shift pada tanggal itu) ditegakkan di
 * SubmitShiftSwapRequest (Application), bukan di sini.
 */
final class SubmitShiftSwapRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counterpart_employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'swap_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
