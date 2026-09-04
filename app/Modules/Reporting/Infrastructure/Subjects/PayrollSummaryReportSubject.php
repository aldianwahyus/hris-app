<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Subjects;

use App\Modules\Reporting\Domain\ReportColumn;
use App\Modules\Reporting\Infrastructure\QueryableReportSubject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PayrollSummaryReportSubject implements QueryableReportSubject
{
    public function key(): string
    {
        return 'ringkasan-payroll';
    }

    public function label(): string
    {
        return 'Ringkasan Payroll';
    }

    public function columns(): array
    {
        return [
            'nrp' => new ReportColumn('nrp', 'NRP', 'e.nrp'),
            'full_name' => new ReportColumn('full_name', 'Nama Lengkap', 'e.full_name'),
            'office_name' => new ReportColumn('office_name', 'Kantor', 'o.name'),
            'period' => new ReportColumn('period', 'Periode', 'r.period'),
            'imbalan_kerja_cents' => new ReportColumn('imbalan_kerja_cents', 'Imbalan Kerja (Rp)', 's.imbalan_kerja_cents'),
            'iuran_pensiun_pegawai_cents' => new ReportColumn('iuran_pensiun_pegawai_cents', 'Iuran Pensiun Pegawai (Rp)', 's.iuran_pensiun_pegawai_cents'),
            'iuran_tht_pegawai_cents' => new ReportColumn('iuran_tht_pegawai_cents', 'Iuran THT Pegawai (Rp)', 's.iuran_tht_pegawai_cents'),
            'iuran_tht_bank_cents' => new ReportColumn('iuran_tht_bank_cents', 'Iuran THT Bank (Rp)', 's.iuran_tht_bank_cents'),
            'take_home_partial_cents' => new ReportColumn('take_home_partial_cents', 'Gaji Bersih (Rp)', 's.take_home_partial_cents'),
            'run_status' => new ReportColumn('run_status', 'Status Payroll Run', 'r.status'),
        ];
    }

    public function dateColumn(): string
    {
        return 'r.period';
    }

    public function statusColumn(): string
    {
        return 'r.status';
    }

    public function statusOptions(): array
    {
        return [
            'draft' => 'Draf',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];
    }

    public function query(?string $officeId): Builder
    {
        $query = DB::table('pay_payslips as s')
            ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id');

        if ($officeId !== null) {
            $query->where('r.office_id', $officeId);
        }

        return $query;
    }
}
