<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Struktur organisasi PER KANTOR/UNIT (bukan bagan bank-wide sekaligus)
 * — pilih satu KC/KCP, atau satu Divisi (khusus Kantor Pusat, satu-
 * satunya office_type=head_office; KC/KCP tidak punya konsep divisi).
 *
 * Hierarki ANTAR ORANG dibangun dari emp_employees.supervisor_id — kolom
 * ini MURNI untuk tampilan bagan, TIDAK dipakai untuk wewenang
 * persetujuan (itu tetap AccessPolicy/OrganizationalScope berbasis
 * kantor seperti sebelumnya, lihat migrasi
 * 2026_08_21_000005_add_supervisor_and_division_to_employees).
 *
 * Akar bagan = pegawai TANPA supervisor_id, ATAU yang supervisor-nya
 * berada DI LUAR cakupan kantor/divisi yang sedang ditampilkan (supaya
 * bagan per unit tetap utuh walau ada pegawai yang atasannya di kantor
 * lain). Avatar TANPA foto (sistem tidak punya penyimpanan foto
 * pegawai) — dipakai inisial nama.
 */
final class OrganizationChartController extends Controller
{
    public function index(): View
    {
        $offices = DB::table('md_offices')
            ->select('id', 'code', 'name', 'office_type')
            ->orderBy('name')
            ->get();

        $headOfficeId = $offices->firstWhere('office_type', 'head_office')?->id;

        $divisions = $headOfficeId === null ? collect() : DB::table('emp_employees')
            ->where('office_id', $headOfficeId)
            ->whereNotNull('division')
            ->distinct()
            ->orderBy('division')
            ->pluck('division');

        $hasUndividedHeadOfficeStaff = $headOfficeId !== null && DB::table('emp_employees')
            ->where('office_id', $headOfficeId)
            ->whereNull('division')
            ->exists();

        return view('admin.org-chart', compact('offices', 'headOfficeId', 'divisions', 'hasUndividedHeadOfficeStaff'));
    }

    public function show(Request $request, string $officeId): View
    {
        [$office, $divisi, $tree, $judulUnit] = $this->buildChart($officeId, $request);

        return view('admin.org-chart-show', [
            'office' => $office,
            'divisi' => $divisi,
            'tree' => $tree,
            'judulUnit' => $judulUnit,
        ]);
    }

    public function pdf(Request $request, string $officeId): Response
    {
        [$office, $divisi, $tree, $judulUnit] = $this->buildChart($officeId, $request);

        $pdf = Pdf::loadView('admin.org-chart-pdf', [
            'office' => $office,
            'divisi' => $divisi,
            'tree' => $tree,
            'judulUnit' => $judulUnit,
        ]);

        return $pdf->download('struktur-organisasi-'.str($judulUnit)->slug().'.pdf');
    }

    /** @return array{0: object, 1: ?string, 2: Collection<int, mixed>, 3: string} */
    private function buildChart(string $officeId, Request $request): array
    {
        $office = DB::table('md_offices')->where('id', $officeId)->first();

        abort_if($office === null, 404);

        $divisi = $office->office_type === 'head_office'
            ? $request->string('divisi')->toString() ?: null
            : null;

        $query = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->where('e.office_id', $officeId)
            ->select('e.id', 'e.full_name', 'e.nrp', 'e.supervisor_id', 'e.division', 'p.name as position_name');

        if ($office->office_type === 'head_office') {
            $divisi === null ? $query->whereNull('e.division') : $query->where('e.division', $divisi);
        }

        $employees = $query->orderBy('e.full_name')->get()->keyBy('id');

        $childrenOf = [];
        foreach ($employees as $employee) {
            $parentKey = ($employee->supervisor_id !== null && $employees->has($employee->supervisor_id))
                ? $employee->supervisor_id
                : 'root';

            $childrenOf[$parentKey][] = $employee;
        }

        $buildNode = function ($employee, array $visited) use (&$buildNode, $childrenOf) {
            // Pagar siklus — supervisor_id ditetapkan manual lewat form,
            // secara teori bisa membentuk lingkaran (A atasan B, B
            // atasan A). Bukan mustahil terjadi karena tidak ada
            // validasi anti-siklus di sisi penyimpanan.
            if (in_array($employee->id, $visited, true)) {
                return null;
            }

            $visited[] = $employee->id;

            $children = collect($childrenOf[$employee->id] ?? [])
                ->map(fn ($child) => $buildNode($child, $visited))
                ->filter()
                ->values();

            return (object) [
                'employee' => $employee,
                'initials' => $this->initials($employee->full_name),
                'children' => $children,
            ];
        };

        $tree = collect($childrenOf['root'] ?? [])->map(fn ($e) => $buildNode($e, []))->filter()->values();

        $judulUnit = $office->office_type === 'head_office'
            ? $office->name.($divisi !== null ? " — {$divisi}" : ' — Tanpa Divisi')
            : $office->name;

        return [$office, $divisi, $tree, $judulUnit];
    }

    private function initials(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.($parts !== [] && count($parts) > 1 ? $last : ''));
    }
}
