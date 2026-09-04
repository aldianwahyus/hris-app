<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Subjects;

use App\Modules\Reporting\Domain\ReportColumn;
use App\Modules\Reporting\Infrastructure\QueryableReportSubject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AssetReportSubject implements QueryableReportSubject
{
    public function key(): string
    {
        return 'aset';
    }

    public function label(): string
    {
        return 'Aset';
    }

    public function columns(): array
    {
        // current_holder_name — subquery TERKORELASI dengan LIMIT 1, BUKAN
        // join ke ast_assignments (join akan menggandakan baris aset bila
        // riwayat penugasannya lebih dari satu, subquery skalar tidak).
        $currentHolderSql = '(select emp2.full_name from ast_assignments a2 '
            .'join emp_employees emp2 on emp2.id = a2.employee_id '
            .'where a2.asset_id = a.id and a2.returned_at is null limit 1)';

        return [
            'asset_code' => new ReportColumn('asset_code', 'Kode Aset', 'a.asset_code'),
            'name' => new ReportColumn('name', 'Nama Aset', 'a.name'),
            'category' => new ReportColumn('category', 'Kategori', 'a.category'),
            'brand_model' => new ReportColumn('brand_model', 'Merek/Model', 'a.brand_model'),
            'serial_number' => new ReportColumn('serial_number', 'Nomor Seri', 'a.serial_number'),
            'condition' => new ReportColumn('condition', 'Kondisi', 'a.condition'),
            'status' => new ReportColumn('status', 'Status', 'a.status'),
            'office_name' => new ReportColumn('office_name', 'Kantor', 'o.name'),
            'purchase_date' => new ReportColumn('purchase_date', 'Tanggal Pembelian', 'a.purchase_date'),
            'current_holder_name' => new ReportColumn('current_holder_name', 'Dipegang Oleh', $currentHolderSql),
        ];
    }

    public function dateColumn(): string
    {
        return 'a.purchase_date';
    }

    public function statusColumn(): string
    {
        return 'a.status';
    }

    public function statusOptions(): array
    {
        return [
            'tersedia' => 'Tersedia',
            'dipakai' => 'Dipakai',
            'perbaikan' => 'Perbaikan',
            'dihapuskan' => 'Dihapuskan',
        ];
    }

    public function query(?string $officeId): Builder
    {
        $query = DB::table('ast_assets as a')
            ->join('md_offices as o', 'o.id', '=', 'a.office_id');

        if ($officeId !== null) {
            $query->where('a.office_id', $officeId);
        }

        return $query;
    }
}
