<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Subjects;

use App\Modules\Reporting\Domain\ReportColumn;
use App\Modules\Reporting\Infrastructure\QueryableReportSubject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EmployeeReportSubject implements QueryableReportSubject
{
    public function key(): string
    {
        return 'pegawai';
    }

    public function label(): string
    {
        return 'Data Pegawai';
    }

    public function columns(): array
    {
        return [
            'nrp' => new ReportColumn('nrp', 'NRP', 'e.nrp'),
            'full_name' => new ReportColumn('full_name', 'Nama Lengkap', 'e.full_name'),
            'email' => new ReportColumn('email', 'Email', 'e.email'),
            'no_telepon' => new ReportColumn('no_telepon', 'No. Telepon', 'e.no_telepon'),
            'office_name' => new ReportColumn('office_name', 'Kantor', 'o.name'),
            'position_name' => new ReportColumn('position_name', 'Jabatan', 'p.name'),
            'employment_status' => new ReportColumn('employment_status', 'Status Kepegawaian', 'e.employment_status'),
            'join_date' => new ReportColumn('join_date', 'Tanggal Bergabung', 'e.join_date'),
        ];
    }

    public function dateColumn(): string
    {
        return 'e.join_date';
    }

    public function statusColumn(): string
    {
        return 'e.employment_status';
    }

    public function statusOptions(): array
    {
        return [
            'tetap' => 'Tetap',
            'trainee' => 'Trainee',
            'kontrak' => 'Kontrak',
            'outsource' => 'Outsource',
        ];
    }

    public function query(?string $officeId): Builder
    {
        $query = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id');

        if ($officeId !== null) {
            $query->where('e.office_id', $officeId);
        }

        return $query;
    }
}
