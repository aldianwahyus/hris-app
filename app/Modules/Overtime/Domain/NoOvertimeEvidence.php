<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Tidak ditemukan bukti lembur pada catatan absensi (DEC-37) — jam
 * lembur TIDAK diketik sendiri oleh pemohon, melainkan dibaca dari
 * absen pulang aktual yang melewati jam kerja resmi.
 */
final class NoOvertimeEvidence extends DomainException
{
    public static function forDate(DateTimeImmutable $workDate): self
    {
        return new self(sprintf(
            'Pegawai tersebut tidak ada lembur pada tanggal %s — tidak ditemukan absen pulang yang melewati jam kerja resmi hari itu.',
            $workDate->format('d-m-Y'),
        ));
    }
}
