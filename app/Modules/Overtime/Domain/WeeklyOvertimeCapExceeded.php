<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

use DomainException;

final class WeeklyOvertimeCapExceeded extends DomainException
{
    public static function forRequest(float $requestedHours, float $alreadyReservedHours, float $capHours): self
    {
        $req = rtrim(rtrim(number_format($requestedHours, 1, ',', '.'), '0'), ',');
        $reserved = rtrim(rtrim(number_format($alreadyReservedHours, 1, ',', '.'), '0'), ',');
        $cap = rtrim(rtrim(number_format($capHours, 1, ',', '.'), '0'), ',');

        return new self(
            "Pengajuan {$req} jam melampaui plafon lembur mingguan ({$cap} jam). "
            ."Sudah tercatat {$reserved} jam (disetujui + menunggu) pada minggu ini."
        );
    }
}
