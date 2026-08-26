<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestOutsideAttendanceForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
