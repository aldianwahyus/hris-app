<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application;

use App\Modules\Attendance\Domain\AttendanceAction;
use App\Modules\Attendance\Domain\AttendanceStatus;

/**
 * Hasil satu kali absen — dipakai Interfaces untuk menyusun pesan ke
 * pegawai. $status HANYA terisi untuk AttendanceAction::CheckIn (hadir/
 * telat ditentukan SAAT masuk, lihat AttendanceDayPolicy) — null untuk
 * Istirahat/Kembali/Pulang, yang tidak mengubah status hari itu.
 */
final readonly class AttendanceOutcome
{
    public function __construct(
        public AttendanceAction $action,
        public ?AttendanceStatus $status,
    ) {}
}
