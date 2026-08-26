<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DomainException;

final class AlreadyCompletedToday extends DomainException
{
    public static function create(): self
    {
        return new self('Absensi hari ini sudah lengkap (masuk dan pulang tercatat).');
    }
}
