<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Subjects;

use App\Modules\Reporting\Domain\ReportColumn;
use App\Modules\Reporting\Infrastructure\QueryableReportSubject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class LeaveReportSubject implements QueryableReportSubject
{
    public function key(): string
    {
        return 'cuti';
    }

    public function label(): string
    {
        return 'Cuti';
    }

    public function columns(): array
    {
        return [
            'request_number' => new ReportColumn('request_number', 'Nomor Pengajuan', 'lr.request_number'),
            'nrp' => new ReportColumn('nrp', 'NRP', 'e.nrp'),
            'full_name' => new ReportColumn('full_name', 'Nama Lengkap', 'e.full_name'),
            'office_name' => new ReportColumn('office_name', 'Kantor', 'o.name'),
            'leave_type' => new ReportColumn('leave_type', 'Jenis Cuti', 'lr.leave_type'),
            'start_date' => new ReportColumn('start_date', 'Mulai', 'lr.start_date'),
            'end_date' => new ReportColumn('end_date', 'Selesai', 'lr.end_date'),
            'total_days' => new ReportColumn('total_days', 'Jumlah Hari', 'lr.total_days'),
            'status' => new ReportColumn('status', 'Status', 'lr.status'),
            'reason' => new ReportColumn('reason', 'Alasan', 'lr.reason'),
        ];
    }

    public function dateColumn(): string
    {
        return 'lr.start_date';
    }

    public function statusColumn(): string
    {
        return 'lr.status';
    }

    public function statusOptions(): array
    {
        return [
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
        ];
    }

    public function query(?string $officeId): Builder
    {
        $query = DB::table('leave_requests as lr')
            ->join('emp_employees as e', 'e.id', '=', 'lr.employee_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id');

        if ($officeId !== null) {
            $query->where('e.office_id', $officeId);
        }

        return $query;
    }
}
