<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Menentukan status kehadiran dari jam masuk aktual dibandingkan jam
 * kerja resmi + toleransi keterlambatan — keduanya PARAMETER BERVERSI
 * (ATT_WORK_START_TIME, ATT_LATE_GRACE_MINUTES), bukan konstanta,
 * sejalan dengan pola yang sama dipakai Lembur/Cuti.
 *
 * AttendanceStatus::Absen SENGAJA tidak pernah dihasilkan di sini —
 * kelas ini hanya bereaksi atas scan yang benar-benar terjadi. Menandai
 * pegawai "tidak hadir" karena TIDAK ADA scan sepanjang hari adalah
 * pekerjaan job akhir-hari tersendiri (belum dibangun), bukan logika
 * titik-waktu seperti ini.
 */
final readonly class AttendanceDayPolicy
{
    public static function determineCheckInStatus(
        DateTimeImmutable $checkInAt,
        string $workStartTime,
        int $graceMinutes,
    ): AttendanceStatus {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $workStartTime, $m)) {
            throw new InvalidArgumentException("Format ATT_WORK_START_TIME tidak valid: \"{$workStartTime}\" (harus H:i).");
        }

        $threshold = $checkInAt
            ->setTime((int) $m[1], (int) $m[2])
            ->modify("+{$graceMinutes} minutes");

        return $checkInAt > $threshold ? AttendanceStatus::Telat : AttendanceStatus::Hadir;
    }
}
