<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hanya memvalidasi BENTUK masukan. Plafon 18 jam/minggu (DEC-31)
 * ditegakkan di app/Modules/Overtime/Domain, bukan di sini. TIDAK ADA
 * kolom jam di sini dengan sengaja — jam lembur dibaca dari catatan
 * absensi (SubmitOvertimeRequest -> AttendanceRepository), bukan
 * diserahkan dari form (DEC-37).
 */
final class SubmitOvertimeRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'overtime_type' => ['required', 'in:regular,crash_program,shift_picket'],
            'work_date' => ['required', 'date'],
        ];
    }
}
