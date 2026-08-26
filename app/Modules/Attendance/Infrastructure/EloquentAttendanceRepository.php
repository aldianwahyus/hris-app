<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure;

use App\Modules\Attendance\Contracts\AttendanceRepository;
use App\Modules\Attendance\Contracts\OvertimeEvidence;
use App\Modules\Attendance\Domain\AttendanceOvertimePolicy;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentAttendanceRepository implements AttendanceRepository
{
    public function __construct(private readonly ParameterResolver $parameters) {}

    public function overtimeEvidenceOn(string $employeeId, DateTimeImmutable $workDate): ?OvertimeEvidence
    {
        $record = DB::table('att_attendance_records as a')
            ->join('emp_employees as e', 'e.id', '=', 'a.employee_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('a.employee_id', $employeeId)
            ->where('a.work_date', $workDate->format('Y-m-d'))
            ->select('a.check_in_at', 'a.check_out_at', 'o.timezone')
            ->first();

        if ($record === null || $record->check_in_at === null || $record->check_out_at === null) {
            return null;
        }

        $officeTz = new DateTimeZone($record->timezone);

        // Kolom timestamptz dibaca kembali dari sesi Postgres ber-TimeZone
        // UTC (lihat catatan setara di SyncDeviceAttendance) — PHP default
        // timezone juga UTC (config/app.php), jadi penguraian tanpa
        // DateTimeZone eksplisit sudah benar sebelum dikonversi ke zona
        // kantor untuk dibandingkan dengan parameter H:i.
        $checkInAt = (new DateTimeImmutable($record->check_in_at))->setTimezone($officeTz);
        $checkOutAt = (new DateTimeImmutable($record->check_out_at))->setTimezone($officeTz);

        $asOf = AsOfDate::on($workDate);
        $workEndTime = $this->parameters->string('ATT_WORK_END_TIME', $asOf);
        $minQualifyingMinutes = $this->parameters->integer('ATT_OVERTIME_MIN_MINUTES', $asOf);

        $hours = AttendanceOvertimePolicy::determineOvertimeHours($checkInAt, $checkOutAt, $workEndTime, $minQualifyingMinutes);

        if ($hours === null) {
            return null;
        }

        return new OvertimeEvidence($hours, $checkInAt, $checkOutAt);
    }
}
