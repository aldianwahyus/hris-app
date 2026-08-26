<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use DateTimeImmutable;

/**
 * Satu-satunya pintu bagi modul lain untuk membaca data absensi
 * (M-1/M-2 — ModuleBoundaryTest: modul lain hanya boleh bergantung
 * pada Contracts/, tidak pernah pada Domain/Infrastructure di sini).
 */
interface AttendanceRepository
{
    /**
     * NULL bila pegawai tidak memiliki catatan absen masuk DAN pulang
     * pada $workDate, atau jam pulangnya tidak melewati jam kerja
     * resmi + ambang minimal — artinya tidak ada lembur tercatat hari
     * itu.
     */
    public function overtimeEvidenceOn(string $employeeId, DateTimeImmutable $workDate): ?OvertimeEvidence;
}
