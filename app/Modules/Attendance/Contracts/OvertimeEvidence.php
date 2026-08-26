<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use DateTimeImmutable;

/**
 * Bukti realisasi lembur pada satu tanggal — jam pulang aktual sudah
 * melewati jam kerja resmi + ambang minimal. Dipakai modul Overtime
 * (M-1/M-2 — ModuleBoundaryTest) untuk memverifikasi pengajuan lembur
 * terhadap catatan absensi sungguhan, bukan jam yang diketik sendiri
 * oleh pemohon (DEC-37).
 */
final readonly class OvertimeEvidence
{
    public function __construct(
        public float $hours,
        public DateTimeImmutable $checkInAt,
        public DateTimeImmutable $checkOutAt,
    ) {}
}
