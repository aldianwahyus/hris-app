<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

enum OvertimeType: string
{
    case Regular = 'regular';
    case CrashProgram = 'crash_program';
    case ShiftPicket = 'shift_picket';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Lembur Biasa',
            self::CrashProgram => 'Crash Program',
            self::ShiftPicket => 'Shift / Piket',
        };
    }
}
