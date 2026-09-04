<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application;

use App\Modules\Reporting\Infrastructure\QueryableReportSubject;
use App\Modules\Reporting\Infrastructure\Subjects\AssetReportSubject;
use App\Modules\Reporting\Infrastructure\Subjects\AttendanceReportSubject;
use App\Modules\Reporting\Infrastructure\Subjects\EmployeeReportSubject;
use App\Modules\Reporting\Infrastructure\Subjects\LeaveReportSubject;
use App\Modules\Reporting\Infrastructure\Subjects\PayrollSummaryReportSubject;

/**
 * Report Builder (Fase 2) — registry SEMUA subjek laporan yang boleh
 * dipilih pengguna. Menambah subjek baru = tambah satu kelas
 * QueryableReportSubject + satu baris di sini, TIDAK menyentuh
 * GenerateReport.
 */
final class ReportSubjectRegistry
{
    /** @var array<string, QueryableReportSubject> */
    private readonly array $subjects;

    public function __construct(
        EmployeeReportSubject $employee,
        AttendanceReportSubject $attendance,
        LeaveReportSubject $leave,
        PayrollSummaryReportSubject $payrollSummary,
        AssetReportSubject $asset,
    ) {
        $this->subjects = [
            $employee->key() => $employee,
            $attendance->key() => $attendance,
            $leave->key() => $leave,
            $payrollSummary->key() => $payrollSummary,
            $asset->key() => $asset,
        ];
    }

    /** @return array<string, QueryableReportSubject> */
    public function all(): array
    {
        return $this->subjects;
    }

    public function find(string $key): ?QueryableReportSubject
    {
        return $this->subjects[$key] ?? null;
    }
}
