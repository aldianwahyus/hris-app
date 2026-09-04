<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Subjects;

use App\Modules\Reporting\Domain\ReportColumn;
use App\Modules\Reporting\Infrastructure\QueryableReportSubject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AttendanceReportSubject implements QueryableReportSubject
{
    public function key(): string
    {
        return 'absensi';
    }

    public function label(): string
    {
        return 'Absensi';
    }

    public function columns(): array
    {
        return [
            'nrp' => new ReportColumn('nrp', 'NRP', 'e.nrp'),
            'full_name' => new ReportColumn('full_name', 'Nama Lengkap', 'e.full_name'),
            'office_name' => new ReportColumn('office_name', 'Kantor', 'o.name'),
            'work_date' => new ReportColumn('work_date', 'Tanggal', 'ar.work_date'),
            'check_in_at' => new ReportColumn('check_in_at', 'Jam Masuk', 'ar.check_in_at'),
            'check_out_at' => new ReportColumn('check_out_at', 'Jam Pulang', 'ar.check_out_at'),
            'status' => new ReportColumn('status', 'Status', 'ar.status'),
        ];
    }

    public function dateColumn(): string
    {
        return 'ar.work_date';
    }

    public function statusColumn(): string
    {
        return 'ar.status';
    }

    public function statusOptions(): array
    {
        return [
            'hadir' => 'Hadir',
            'telat' => 'Telat',
            'absen' => 'Absen',
        ];
    }

    public function query(?string $officeId): Builder
    {
        $query = DB::table('att_attendance_records as ar')
            ->join('emp_employees as e', 'e.id', '=', 'ar.employee_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id');

        if ($officeId !== null) {
            $query->where('e.office_id', $officeId);
        }

        return $query;
    }
}
