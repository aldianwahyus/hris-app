<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Record Pegawai" — laporan RINCIAN POSISI TERAKHIR seluruh kantor,
 * per bulan yang dipilih. MURNI laporan (TIDAK ADA form input di sini
 * sama sekali) — dibentuk otomatis dari emp_position_history, yang
 * diisi otomatis tiap kali persetujuan SK Mutasi/Promosi benar-benar
 * mengubah office_id/position_id/person_grade/job_grade (lihat
 * DecideEmployeeProfileChange::recordPositionHistoryIfChanged()).
 *
 * KETERBATASAN JUJUR: emp_position_history baru ada sejak
 * FEATURE_RELEASE_DATE (migrasi backfill 2026_09_18_000004) — baris
 * SEBELUM tanggal itu adalah PROYEKSI MUNDUR posisi terkini saat
 * backfill, BUKAN riwayat sesungguhnya (sistem lama tidak pernah
 * merekamnya). Laporan untuk bulan sebelum tanggal itu menampilkan
 * peringatan eksplisit di view — TIDAK didiamkan seolah-olah akurat.
 */
final class EmployeePositionRecordController extends Controller
{
    private const FEATURE_RELEASE_DATE = '2026-09-18';

    public function index(Request $request): View
    {
        $bulan = $this->bulanFilter($request);
        $endOfMonth = (DateTimeImmutable::createFromFormat('!Y-m', $bulan) ?: new DateTimeImmutable('first day of this month'))
            ->modify('last day of this month');

        return view('admin.employee-position-record', [
            'bulan' => $bulan,
            'sebelumRilis' => $endOfMonth->format('Y-m-d') < self::FEATURE_RELEASE_DATE,
            'tanggalRilis' => self::FEATURE_RELEASE_DATE,
            'rows' => $this->positionsAsOf($endOfMonth),
        ]);
    }

    private function bulanFilter(Request $request): string
    {
        $value = $request->string('bulan')->toString();
        $parsed = DateTimeImmutable::createFromFormat('!Y-m', $value);

        return $parsed !== false ? $parsed->format('Y-m') : now()->format('Y-m');
    }

    /**
     * Baris TERAKHIR (effective_from paling akhir yang <= akhir bulan
     * dipilih) per pegawai — pola "as-of-date" sama seperti
     * EloquentTerRateRepository/EloquentSalaryScaleRepository, hanya
     * beda bentuk (di sini "baris terakhir sebelum tanggal", bukan
     * rentang effective_from/effective_to per baris) sehingga
     * memakai DISTINCT ON (Postgres — aplikasi ini 100% Postgres,
     * lihat catatan lain di seluruh basis kode) alih-alih overlap
     * rentang.
     *
     * Pegawai yang sudah dihapus-lunak (deleted_at) SENGAJA dikecualikan
     * — laporan ini mencerminkan roster organisasi SAAT INI beserta
     * riwayat posisinya, bukan mantan pegawai.
     *
     * @return Collection<int, \stdClass>
     */
    private function positionsAsOf(DateTimeImmutable $endOfMonth): Collection
    {
        $rows = DB::table('emp_position_history as h')
            ->join('emp_employees as e', 'e.id', '=', 'h.employee_id')
            ->join('md_offices as o', 'o.id', '=', 'h.office_id')
            ->join('md_positions as p', 'p.id', '=', 'h.position_id')
            ->whereNull('e.deleted_at')
            ->where('h.effective_from', '<=', $endOfMonth->format('Y-m-d'))
            ->selectRaw(
                'DISTINCT ON (h.employee_id) h.employee_id, e.full_name, e.nrp, '.
                'o.name as office_name, p.name as position_name, '.
                'h.person_grade, h.job_grade, h.effective_from'
            )
            ->orderBy('h.employee_id')
            ->orderByDesc('h.effective_from')
            ->get();

        return $rows->sortBy([['office_name', 'asc'], ['full_name', 'asc']])->values();
    }
}
