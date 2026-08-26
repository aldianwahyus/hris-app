<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain;

use App\Shared\Temporal\Domain\AsOfDate;
use DomainException;

final class SalaryStepNotFound extends DomainException
{
    public static function forStep(int $personGrade, int $step, AsOfDate $asOf): self
    {
        return new self(sprintf(
            'Skala imbalan kerja untuk Person Grade %d baris %d belum tersedia per %s — '
            .'kemungkinan baris di atas 1 yang menunggu Lampiran II lengkap (baru baris 1 yang disemai).',
            $personGrade,
            $step,
            $asOf->toDateString(),
        ));
    }
}
