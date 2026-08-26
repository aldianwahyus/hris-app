<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Http\Requests;

use App\Modules\Attendance\Domain\AttendanceAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Hanya memvalidasi BENTUK masukan. Validasi urutan aksi yang sah
 * (mis. tidak bisa Kembali sebelum Istirahat) dan jendela waktu
 * Istirahat/Kembali ditegakkan di app/Modules/Attendance/Domain, bukan
 * di sini — begitu juga radius kantor (geofence).
 */
final class RecordGpsAttendanceForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'action' => ['required', new Enum(AttendanceAction::class)],
        ];
    }
}
