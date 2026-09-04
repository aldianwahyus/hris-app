<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Support;

use App\Modules\Survey\Application\ComputeSurveyResults;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analitik Tenaga Kerja (Fase 2) — BERBASIS ATURAN TRANSPARAN, BUKAN
 * machine learning. Skor eNPS dihitung ULANG lewat ComputeSurveyResults
 * yang SUDAH ADA (rumus %promoter−%detractor SATU tempat, tidak
 * diduplikasi di sini) — bank-wide saja (TIDAK ada lingkup kantor,
 * pola SAMA HcDashboardController).
 */
final class WorkforceAnalytics
{
    public function __construct(private readonly ComputeSurveyResults $surveyResults) {}

    /** @return array<int, array{label: string, value: int}> 12 bulan terakhir, akhir bulan */
    public function headcountTrend(): array
    {
        $points = [];

        for ($i = 11; $i >= 0; $i--) {
            $endOfMonth = Carbon::now()->subMonthsNoOverflow($i)->endOfMonth();

            $points[] = [
                'label' => $endOfMonth->translatedFormat('M Y'),
                'value' => $this->headcountAsOf($endOfMonth),
            ];
        }

        return $points;
    }

    /** Pemisahan (separated_at terisi) 12 bulan terakhir, dibagi rata-rata headcount awal+akhir periode. */
    public function turnoverRate12Months(): float
    {
        $now = Carbon::now();
        $twelveMonthsAgo = $now->copy()->subMonths(12);

        $separations = DB::table('emp_employees')
            ->whereNotNull('separated_at')
            ->where('separated_at', '>=', $twelveMonthsAgo)
            ->count();

        $startHeadcount = $this->headcountAsOf($twelveMonthsAgo);
        $endHeadcount = $this->headcountAsOf($now);
        $avgHeadcount = ($startHeadcount + $endHeadcount) / 2;

        if ($avgHeadcount <= 0) {
            return 0.0;
        }

        return round(($separations / $avgHeadcount) * 100, 1);
    }

    /** Rata-rata masa kerja (tahun) pegawai AKTIF (belum separated_at). */
    public function averageTenureYears(): float
    {
        $joinDates = DB::table('emp_employees')->whereNull('separated_at')->pluck('join_date');

        if ($joinDates->isEmpty()) {
            return 0.0;
        }

        $now = Carbon::now();
        $totalDays = $joinDates->sum(fn ($date) => Carbon::parse($date)->diffInDays($now));

        return round(($totalDays / $joinDates->count()) / 365.25, 1);
    }

    /**
     * Skor eNPS per survei eNPS yang SUDAH selesai, urut kronologis —
     * SATU titik per pelaksanaan survei (BUKAN dipaksa ke grid bulanan,
     * eNPS tidak selalu dijalankan tiap bulan).
     *
     * @return array<int, array{label: string, value: float}>
     */
    public function enpsTrend(): array
    {
        $surveys = DB::table('svy_surveys')
            ->where('type', 'enps')
            ->where('status', 'selesai')
            ->orderBy('end_date')
            ->get();

        $points = [];

        foreach ($surveys as $survey) {
            $results = $this->surveyResults->handle($survey->id);
            $npsQuestion = collect($results['questions'])->firstWhere('question_type', 'nps_0_10');

            if ($npsQuestion === null) {
                continue;
            }

            $points[] = [
                'label' => Carbon::parse($survey->end_date)->translatedFormat('M Y'),
                'value' => (float) $npsQuestion['summary']['score'],
            ];
        }

        return $points;
    }

    /**
     * Indikator risiko keluar — ATURAN TRANSPARAN (BUKAN prediksi machine
     * learning): pegawai aktif, masa kerja 1–7 tahun (rentang umum risiko
     * turnover), DAN tidak ada cuti disetujui dalam 6 bulan terakhir.
     * Sengaja TIDAK memakai sentimen survei per-pegawai — sebagian besar
     * survei eNPS bersifat anonim (is_anonymous), mengaitkan jawaban
     * anonim ke pegawai tertentu akan membocorkan identitas responden.
     *
     * @return Collection<int, \stdClass>
     */
    public function atRiskEmployees(): Collection
    {
        $now = Carbon::now();
        $sixMonthsAgo = $now->copy()->subMonths(6);

        return DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->whereNull('e.separated_at')
            ->whereDate('e.join_date', '<=', $now->copy()->subYear())
            ->whereDate('e.join_date', '>=', $now->copy()->subYears(7))
            ->whereNotExists(function ($query) use ($sixMonthsAgo) {
                $query->select(DB::raw(1))
                    ->from('leave_requests as lr')
                    ->whereColumn('lr.employee_id', 'e.id')
                    ->where('lr.status', 'approved')
                    ->whereDate('lr.start_date', '>=', $sixMonthsAgo);
            })
            ->select('e.id', 'e.nrp', 'e.full_name', 'o.name as office_name', 'e.join_date')
            ->orderBy('e.full_name')
            ->get();
    }

    private function headcountAsOf(Carbon $date): int
    {
        return DB::table('emp_employees')
            ->whereDate('join_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('separated_at')->orWhere('separated_at', '>', $date);
            })
            ->count();
    }
}
