<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Menyemai catatan absensi yang MEMBUKTIKAN lembur — jam masuk 08:00,
 * jam pulang (ATT_WORK_END_TIME default 17:00) + $overtimeHours, di
 * zona waktu kantor pegawai. Dipakai berkas uji yang sebelumnya
 * mengirim jam lembur secara manual (plannedHours) ke
 * SubmitOvertimeRequest::handle() — kini jam itu WAJIB berasal dari
 * absensi sungguhan (DEC-37).
 */
trait SeedsOvertimeAttendance
{
    private function seedOvertimeAttendance(string $employeeId, DateTimeImmutable $workDate, float $overtimeHours): void
    {
        $office = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $employeeId)
            ->select('o.timezone')
            ->first();

        $tz = new DateTimeZone($office->timezone);
        $utc = new DateTimeZone('UTC');
        $dateStr = $workDate->format('Y-m-d');

        $checkInLocal = new DateTimeImmutable("{$dateStr} 08:00:00", $tz);
        $checkOutLocal = (new DateTimeImmutable("{$dateStr} 17:00:00", $tz))
            ->modify(sprintf('+%d minutes', (int) round($overtimeHours * 60)));

        DB::table('att_attendance_records')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'work_date' => $dateStr,
            'check_in_at' => $checkInLocal->setTimezone($utc),
            'check_in_source' => 'gps',
            'check_out_at' => $checkOutLocal->setTimezone($utc),
            'check_out_source' => 'gps',
            'status' => 'hadir',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }
}
