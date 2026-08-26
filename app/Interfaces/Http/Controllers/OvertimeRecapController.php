<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\CsvExport;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap biaya lembur (SPKL disetujui/dicairkan) — hr_admin melihat
 * kantornya sendiri (lingkup OFFICE, ARCH-001 §6.2), hr_approver
 * melihat seluruh bank (lingkup BANK_WIDE). Hanya-baca; amount_cents
 * sudah terisi penuh untuk Lembur Biasa/Shift/Crash Program (lihat
 * SubmitOvertimeRequest::resolveTariff()) — tidak ada komponen
 * "pending" seperti Payroll karena tarif lembur sudah lengkap
 * diregulasi (SK KEP/1827/06/64/2026).
 *
 * @phpstan-type OvertimeRecapRow object{
 *   id: string, spkl_number: string, overtime_type: string, work_date: string,
 *   payable_hours: string, amount_cents: int, status: string,
 *   disbursed_at: ?string, disbursement_reference: ?string,
 *   employee_id: string, office_id: string, full_name: string, nrp: string,
 * }
 */
final class OvertimeRecapController extends Controller
{
    public function __construct(private readonly CurrentActor $actor) {}

    public function index(Request $request): View
    {
        $bulan = $request->string('bulan')->toString() ?: now()->format('Y-m');

        [$rows, $office] = $this->scopedRows($bulan);

        $perPegawai = $rows->groupBy('employee_id')->map(function ($group) {
            /** @var object{full_name: string, nrp: string} $first */
            $first = $group->firstOrFail();

            return (object) [
                'full_name' => $first->full_name,
                'nrp' => $first->nrp,
                'total_jam' => $group->sum('payable_hours'),
                'total_biaya_cents' => $group->sum('amount_cents'),
                'jumlah_spkl' => $group->count(),
            ];
        })->values();

        $summary = [
            'jumlah_spkl' => $rows->count(),
            'total_jam' => $rows->sum('payable_hours'),
            'total_biaya_cents' => $rows->sum('amount_cents'),
        ];

        // Dua segmen terpisah — bukan satu tabel dicampur status —
        // supaya jelas kelihatan mana yang masih menunggu dibayar dan
        // mana yang sudah cair, sesuai permintaan eksplisit.
        $menunggu = $rows->where('status', 'approved')->values();
        $dicairkan = $rows->where('status', 'disbursed')->values();

        return view('admin.overtime-recap', compact('office', 'bulan', 'perPegawai', 'summary', 'menunggu', 'dicairkan'));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $bulan = $request->string('bulan')->toString() ?: now()->format('Y-m');

        [$rows] = $this->scopedRows($bulan);

        /** @var array<int, array<int, string>> $csvRows */
        $csvRows = $rows->map(fn ($r) => [
            $r->spkl_number,
            $r->full_name,
            $r->nrp,
            $r->overtime_type,
            $r->work_date,
            $r->payable_hours,
            number_format($r->amount_cents / 100, 0, ',', '.'),
            $r->status === 'disbursed' ? 'Sudah Dicairkan' : 'Menunggu Pembayaran',
            $r->disbursement_reference ?? '',
            $r->disbursed_at ?? '',
        ])->all();

        return CsvExport::download(
            "rekap-biaya-lembur-{$bulan}.csv",
            ['No. SPKL', 'Nama Pegawai', 'NRP', 'Jenis Lembur', 'Tanggal Kerja', 'Jam', 'Nominal (Rp)', 'Status', 'Referensi Pembayaran', 'Tanggal Dicairkan'],
            $csvRows,
        );
    }

    /**
     * @return array{0: Collection<int, OvertimeRecapRow>, 1: ?object}
     */
    private function scopedRows(string $bulan): array
    {
        $query = $this->baseQuery($bulan);
        $office = null;

        if ($this->actor->hasRole(Role::HrAdmin->value)) {
            $officeId = $this->actor->officeId();
            abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');
            $office = DB::table('md_offices')->where('id', $officeId)->first();
            $query->where('e.office_id', $officeId);
        }

        /** @var Collection<int, OvertimeRecapRow> $rows */
        $rows = $query->orderBy('r.work_date')->orderBy('e.full_name')->get();

        return [$rows, $office];
    }

    private function baseQuery(string $bulan): Builder
    {
        return DB::table('ovt_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->select(
                'r.id', 'r.spkl_number', 'r.overtime_type', 'r.work_date',
                'r.payable_hours', 'r.amount_cents', 'r.status',
                'r.disbursed_at', 'r.disbursement_reference',
                'e.id as employee_id', 'e.office_id', 'e.full_name', 'e.nrp'
            )
            // 'disbursed' ikut serta (bukan cuma 'approved') — rekap ini
            // sekarang jadi daftar per-SPKL dengan status bayar/belum,
            // bukan cuma total biaya yang masih menunggu.
            ->whereIn('r.status', ['approved', 'disbursed'])
            ->whereRaw("to_char(r.work_date, 'YYYY-MM') = ?", [$bulan]);
    }
}
