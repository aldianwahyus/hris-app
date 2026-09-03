<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\CsvExport;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap Penghasilan — total penghasilan per pegawai per bulan, dijumlah
 * dari 4 sumber yang SUDAH ADA (bukan tabel baru): gaji bersih (payroll
 * run yang sudah disetujui), lembur (SPKL disetujui/dicairkan, pola
 * SAMA OvertimeRecapController), SPPD (pengajuan disetujui/dicairkan,
 * rumus gross SAMA PERSIS ProcessSppdPaymentBatch), dan Bekal Cuti
 * (satu-satunya komponen tunai yang menempel pada Cuti — Cuti sendiri
 * tidak memotong penghasilan, jadi bukan "gaji dikurangi").
 *
 * Lingkup SAMA overtime-recap.view: hr_admin melihat kantornya sendiri
 * (OFFICE), hr_approver melihat seluruh bank (BANK_WIDE). Hanya-baca,
 * murni menjumlah data yang sudah ada di 4 modul lain — tidak ada
 * Application layer/tabel baru.
 *
 * @phpstan-type IncomeRecapRow object{
 *   employee_id: string, full_name: string, nrp: string,
 *   gaji_cents: int, lembur_cents: int, sppd_cents: int, bekal_cuti_cents: int, total_cents: int,
 * }
 */
final class IncomeRecapController extends Controller
{
    public function __construct(private readonly CurrentActor $actor) {}

    public function index(Request $request): View
    {
        $bulan = $request->string('bulan')->toString() ?: now()->format('Y-m');

        [$rows, $office] = $this->scopedRows($bulan);

        $summary = [
            'jumlah_pegawai' => $rows->count(),
            'total_gaji_cents' => $rows->sum('gaji_cents'),
            'total_lembur_cents' => $rows->sum('lembur_cents'),
            'total_sppd_cents' => $rows->sum('sppd_cents'),
            'total_bekal_cuti_cents' => $rows->sum('bekal_cuti_cents'),
            'total_keseluruhan_cents' => $rows->sum('total_cents'),
        ];

        return view('admin.income-recap', compact('office', 'bulan', 'rows', 'summary'));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $bulan = $request->string('bulan')->toString() ?: now()->format('Y-m');

        [$rows] = $this->scopedRows($bulan);

        /** @var array<int, array<int, string>> $csvRows */
        $csvRows = $rows->map(fn ($r) => [
            $r->full_name,
            $r->nrp,
            number_format($r->gaji_cents / 100, 0, ',', '.'),
            number_format($r->lembur_cents / 100, 0, ',', '.'),
            number_format($r->sppd_cents / 100, 0, ',', '.'),
            number_format($r->bekal_cuti_cents / 100, 0, ',', '.'),
            number_format($r->total_cents / 100, 0, ',', '.'),
        ])->all();

        return CsvExport::download(
            "rekap-penghasilan-{$bulan}.csv",
            ['Nama Pegawai', 'NRP', 'Gaji Bersih (Rp)', 'Lembur (Rp)', 'SPPD (Rp)', 'Bekal Cuti (Rp)', 'Total Penghasilan (Rp)'],
            $csvRows,
        );
    }

    /**
     * @return array{0: Collection<int, IncomeRecapRow>, 1: ?object}
     */
    private function scopedRows(string $bulan): array
    {
        $officeId = null;
        $office = null;

        if ($this->actor->hasRole(Role::HrAdmin->value)) {
            $officeId = $this->actor->officeId();
            abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');
            $office = DB::table('md_offices')->where('id', $officeId)->first();
        }

        $employees = DB::table('emp_employees as e')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('e.employment_status', 'tetap')
            ->whereNotNull('e.person_grade')
            ->select('e.id', 'e.full_name', 'e.nrp')
            ->orderBy('e.full_name')
            ->get();

        $gaji = $this->gajiPerEmployee($bulan, $officeId);
        $lembur = $this->lemburPerEmployee($bulan, $officeId);
        $sppd = $this->sppdPerEmployee($bulan, $officeId);
        $bekalCuti = $this->bekalCutiPerEmployee($bulan, $officeId);

        /** @var Collection<int, IncomeRecapRow> $rows */
        $rows = $employees->map(function ($e) use ($gaji, $lembur, $sppd, $bekalCuti) {
            $gajiCents = (int) ($gaji[$e->id] ?? 0);
            $lemburCents = (int) ($lembur[$e->id] ?? 0);
            $sppdCents = (int) ($sppd[$e->id] ?? 0);
            $bekalCutiCents = (int) ($bekalCuti[$e->id] ?? 0);

            return (object) [
                'employee_id' => $e->id,
                'full_name' => $e->full_name,
                'nrp' => $e->nrp,
                'gaji_cents' => $gajiCents,
                'lembur_cents' => $lemburCents,
                'sppd_cents' => $sppdCents,
                'bekal_cuti_cents' => $bekalCutiCents,
                'total_cents' => $gajiCents + $lemburCents + $sppdCents + $bekalCutiCents,
            ];
        })->values();

        return [$rows, $office];
    }

    /**
     * Gaji BERSIH — take_home_partial_cents (dasar payroll, TIDAK PERNAH
     * dimutasi langsung, lihat PayslipController) DITAMBAH/DIKURANGI
     * potongan/tambahan ad-hoc per slip, pola SAMA PERSIS
     * PayslipController::index() supaya angka di sini konsisten dengan
     * yang dilihat pegawai sendiri di slip gajinya.
     *
     * @return array<string, int>
     */
    private function gajiPerEmployee(string $bulan, ?string $officeId): array
    {
        $query = DB::table('pay_payslips as s')
            ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
            ->where('r.status', 'approved')
            ->whereRaw("to_char(r.period, 'YYYY-MM') = ?", [$bulan]);

        if ($officeId !== null) {
            $query->where('r.office_id', $officeId);
        }

        $slips = $query->select('s.id', 's.employee_id', 's.take_home_partial_cents')->get();
        $slipIds = $slips->pluck('id');

        $deductions = DB::table('pay_payslip_deductions')
            ->whereIn('payslip_id', $slipIds)
            ->select('payslip_id', DB::raw('sum(amount_cents) as total'))
            ->groupBy('payslip_id')
            ->pluck('total', 'payslip_id');

        $additions = DB::table('pay_payslip_additions')
            ->whereIn('payslip_id', $slipIds)
            ->select('payslip_id', DB::raw('sum(amount_cents) as total'))
            ->groupBy('payslip_id')
            ->pluck('total', 'payslip_id');

        $result = [];

        foreach ($slips as $slip) {
            $net = (int) $slip->take_home_partial_cents
                + (int) ($additions[$slip->id] ?? 0)
                - (int) ($deductions[$slip->id] ?? 0);

            $result[$slip->employee_id] = ($result[$slip->employee_id] ?? 0) + $net;
        }

        return $result;
    }

    /**
     * Lembur disetujui/dicairkan — pola SAMA PERSIS
     * OvertimeRecapController::baseQuery() (status IN
     * approved,disbursed; berdasar work_date, bukan tanggal keputusan).
     *
     * @return array<string, int>
     */
    private function lemburPerEmployee(string $bulan, ?string $officeId): array
    {
        $query = DB::table('ovt_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->whereIn('r.status', ['approved', 'disbursed'])
            ->whereRaw("to_char(r.work_date, 'YYYY-MM') = ?", [$bulan]);

        if ($officeId !== null) {
            $query->where('e.office_id', $officeId);
        }

        return $query
            ->select('r.employee_id', DB::raw('sum(coalesce(r.amount_cents, 0)) as total'))
            ->groupBy('r.employee_id')
            ->pluck('total', 'employee_id')
            ->all();
    }

    /**
     * SPPD disetujui/dicairkan — rumus gross SAMA PERSIS
     * ProcessSppdPaymentBatch::handle() ($grossCents, sebelum pajak
     * TER), berdasar start_date perjalanan.
     *
     * @return array<string, int>
     */
    private function sppdPerEmployee(string $bulan, ?string $officeId): array
    {
        $query = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->whereIn('r.status', ['approved', 'disbursed'])
            ->whereRaw("to_char(r.start_date, 'YYYY-MM') = ?", [$bulan]);

        if ($officeId !== null) {
            $query->where('e.office_id', $officeId);
        }

        return $query
            ->select('r.employee_id', DB::raw('sum(
                coalesce(r.uang_makan_cents, 0) + coalesce(r.uang_saku_cents, 0)
                + coalesce(r.estimasi_hotel_cents, 0) + coalesce(r.hotel_kompensasi_cents, 0)
                + coalesce(r.estimasi_angkutan_setempat_cents, 0) + coalesce(r.estimasi_transportasi_tujuan_cents, 0)
                + coalesce(r.uang_makan_h1_cents, 0) + coalesce(r.uang_saku_h1_cents, 0)
                + coalesce(r.uang_makan_konsumsi_cents, 0)
            ) as total'))
            ->groupBy('r.employee_id')
            ->pluck('total', 'employee_id')
            ->all();
    }

    /**
     * Bekal Cuti dicairkan — berdasar disbursed_at (kapan UANG-nya
     * keluar), bukan tahun hak (year) — satu-satunya komponen tunai
     * yang menempel pada Cuti (Cuti sendiri tidak memotong penghasilan).
     *
     * @return array<string, int>
     */
    private function bekalCutiPerEmployee(string $bulan, ?string $officeId): array
    {
        $query = DB::table('pay_bekal_cuti_disbursements as d')
            ->join('emp_employees as e', 'e.id', '=', 'd.employee_id')
            ->where('d.status', 'disbursed')
            ->whereRaw("to_char(d.disbursed_at, 'YYYY-MM') = ?", [$bulan]);

        if ($officeId !== null) {
            $query->where('e.office_id', $officeId);
        }

        return $query
            ->select('d.employee_id', DB::raw('sum(coalesce(d.amount_cents, 0)) as total'))
            ->groupBy('d.employee_id')
            ->pluck('total', 'employee_id')
            ->all();
    }
}
