<?php

declare(strict_types=1);

namespace App\Shared\Holiday\Domain;

use DateTimeImmutable;

/**
 * Rujukan kalender hari libur nasional — dipakai lintas modul (Cuti,
 * Absensi, Dasbor HC) untuk mengecualikan akhir pekan & hari libur dari
 * hitungan hari kerja. Satu-satunya sumber kebenaran untuk "apakah
 * tanggal ini hari kerja", supaya definisinya tidak menyimpang antar
 * konsumen (lihat SubmitLeaveRequest, AttendanceRecapController,
 * HcDashboardController).
 */
interface HolidayRepository
{
    public function isHoliday(DateTimeImmutable $date): bool;

    /** @return array<int, Holiday> hari libur di antara $start dan $end, inklusif kedua ujung */
    public function between(DateTimeImmutable $start, DateTimeImmutable $end): array;

    /** Jumlah hari kerja (BUKAN Sabtu/Minggu, BUKAN hari libur) di antara $start dan $end, inklusif kedua ujung. */
    public function countWorkingDays(DateTimeImmutable $start, DateTimeImmutable $end): int;
}
