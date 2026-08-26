<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\CsvExport;
use App\Modules\Lms\Application\ComputeTalentReadiness;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporting + Advanced Analytics (BRD §5.11 + §5.12) — HC
 * (permission:lms-catalog.manage), MURNI baca/agregat dari tabel yang
 * sudah ada, tidak ada migrasi baru. "ROI Pelatihan"/"Predictive
 * Analytics" dari mockup BRD SENGAJA TIDAK dibangun — app ini tidak
 * punya data biaya pelatihan/model nilai-gaji sama sekali; mengarang
 * angka finansial akan jadi data palsu yang menyesatkan pengambilan
 * keputusan (bertentangan dengan tujuan BRD §1). Dashboard menampilkan
 * pesan keterbatasan eksplisit untuk blok itu, bukan angka rekaan.
 */
final class LmsAnalyticsController extends Controller
{
    public function __construct(private readonly ComputeTalentReadiness $readiness) {}

    public function dashboard(): View
    {
        $totalEnrollments = DB::table('lms_enrollments')->where('status', 'approved')->count();
        $lulus = DB::table('lms_enrollments')->where('completion_status', 'lulus')->count();
        $tidakLulus = DB::table('lms_enrollments')->where('completion_status', 'tidak_lulus')->count();
        $completionRate = ($lulus + $tidakLulus) > 0 ? round(($lulus / ($lulus + $tidakLulus)) * 100, 1) : null;

        $activeLearners = DB::table('lms_enrollments')->distinct('employee_id')->count('employee_id');

        $popularCourses = $this->popularCourses();
        $categoryDistribution = $this->categoryDistribution();

        $totalAttempts = DB::table('lms_assessment_attempts')->where('status', 'scored')->count();
        $passedAttempts = DB::table('lms_assessment_attempts')->where('status', 'scored')->where('passed', true)->count();
        $assessmentPassRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : null;

        $talentDistribution = $this->talentReadinessDistribution();
        $topGaps = $this->topOrganizationWideGaps();

        return view('admin.lms-analytics-dashboard', compact(
            'completionRate', 'lulus', 'tidakLulus', 'totalEnrollments', 'activeLearners',
            'popularCourses', 'categoryDistribution', 'assessmentPassRate', 'totalAttempts',
            'talentDistribution', 'topGaps',
        ));
    }

    public function exportDashboard(): StreamedResponse
    {
        $totalEnrollments = DB::table('lms_enrollments')->where('status', 'approved')->count();
        $lulus = DB::table('lms_enrollments')->where('completion_status', 'lulus')->count();
        $tidakLulus = DB::table('lms_enrollments')->where('completion_status', 'tidak_lulus')->count();
        $completionRate = ($lulus + $tidakLulus) > 0 ? round(($lulus / ($lulus + $tidakLulus)) * 100, 1) : null;
        $activeLearners = DB::table('lms_enrollments')->distinct('employee_id')->count('employee_id');
        $totalAttempts = DB::table('lms_assessment_attempts')->where('status', 'scored')->count();
        $passedAttempts = DB::table('lms_assessment_attempts')->where('status', 'scored')->where('passed', true)->count();
        $assessmentPassRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : null;
        $talentDistribution = $this->talentReadinessDistribution();

        /** @var array<int, array<int, string>> $rows */
        $rows = [
            ['Ringkasan', ''],
            ['Total pendaftaran disetujui', (string) $totalEnrollments],
            ['Lulus', (string) $lulus],
            ['Tidak lulus', (string) $tidakLulus],
            ['Tingkat kelulusan (%)', $completionRate !== null ? (string) $completionRate : '-'],
            ['Pembelajar aktif', (string) $activeLearners],
            ['Total percobaan asesmen dinilai', (string) $totalAttempts],
            ['Tingkat lulus asesmen (%)', $assessmentPassRate !== null ? (string) $assessmentPassRate : '-'],
            [],
            ['Kursus terpopuler', ''],
            ...$this->popularCourses()->map(fn ($c) => [$c->title, (string) $c->jumlah])->all(),
            [],
            ['Distribusi kategori', ''],
            ...$this->categoryDistribution()->map(fn ($c) => [$c->kategori, (string) $c->jumlah])->all(),
            [],
            ['Distribusi kesiapan talenta', ''],
            ['Rendah', (string) $talentDistribution['rendah']],
            ['Sedang', (string) $talentDistribution['sedang']],
            ['Tinggi', (string) $talentDistribution['tinggi']],
            ['Belum dihitung', (string) $talentDistribution['belum_dihitung']],
            [],
            ['5 kesenjangan kompetensi organisasi terbesar', ''],
            ...array_map(fn ($g) => [$g['position_name'].' — '.$g['competency_name'], (string) $g['avg_gap']], $this->topOrganizationWideGaps()),
        ];

        return CsvExport::download('dasbor-analitik-lms-'.now()->format('Y-m-d').'.csv', ['Item', 'Nilai'], $rows);
    }

    public function trainingReport(): View
    {
        return view('admin.lms-training-report', ['rows' => $this->trainingReportRows()]);
    }

    public function exportTrainingReport(): StreamedResponse
    {
        return CsvExport::download(
            'laporan-pelatihan-'.now()->format('Y-m-d').'.csv',
            ['Kursus', 'Kode Batch', 'Mulai', 'Selesai', 'Terdaftar', 'Disetujui', 'Lulus', 'Tidak Lulus', 'Tingkat Kelulusan (%)'],
            $this->trainingReportRows()->map(fn ($r) => [
                $r->course_title, $r->batch_code, $r->start_date, $r->end_date,
                $r->terdaftar, $r->disetujui, $r->lulus, $r->tidak_lulus,
                $r->completion_rate ?? '-',
            ])->all(),
        );
    }

    public function evaluationReport(): View
    {
        return view('admin.lms-evaluation-report', ['rows' => $this->evaluationReportRows()]);
    }

    public function exportEvaluationReport(): StreamedResponse
    {
        return CsvExport::download(
            'laporan-evaluasi-'.now()->format('Y-m-d').'.csv',
            ['Asesmen', 'Skor Kelulusan', 'Jumlah Percobaan', 'Rata-rata Skor', 'Jumlah Lulus', 'Jumlah Dinilai', 'Tingkat Kelulusan (%)'],
            $this->evaluationReportRows()->map(fn ($r) => [
                $r->title, $r->passing_score, $r->jumlah_attempt,
                $r->rata_skor !== null ? round((float) $r->rata_skor, 1) : '-',
                $r->jumlah_lulus, $r->jumlah_ternilai, $r->pass_rate ?? '-',
            ])->all(),
        );
    }

    public function competencyReport(): View
    {
        return view('admin.lms-competency-report', ['rows' => $this->competencyReportRows()]);
    }

    public function exportCompetencyReport(): StreamedResponse
    {
        return CsvExport::download(
            'laporan-kompetensi-'.now()->format('Y-m-d').'.csv',
            ['Jabatan', 'Kompetensi', 'Level Dibutuhkan', 'Rata-rata Level Saat Ini', 'Rata-rata Gap'],
            $this->competencyReportRows()->map(fn ($r) => [
                $r->position_name, $r->competency_name, $r->required_level,
                $r->avg_current_level ?? '-', $r->avg_gap ?? '-',
            ])->all(),
        );
    }

    public function talentReport(): View
    {
        $distribution = $this->talentReadinessDistribution();
        $keyPositions = $this->talentKeyPositions();

        return view('admin.lms-talent-report', compact('distribution', 'keyPositions'));
    }

    public function exportTalentReport(): StreamedResponse
    {
        $distribution = $this->talentReadinessDistribution();
        $keyPositions = $this->talentKeyPositions();

        /** @var array<int, array<int, string>> $rows */
        $rows = [
            ['Distribusi kesiapan talenta', ''],
            ['Rendah', (string) $distribution['rendah']],
            ['Sedang', (string) $distribution['sedang']],
            ['Tinggi', (string) $distribution['tinggi']],
            ['Belum dihitung', (string) $distribution['belum_dihitung']],
            [],
            ['Jabatan Kunci', 'Ada Kandidat Siap Sekarang', 'Jumlah Kandidat'],
            ...$keyPositions->map(fn ($p) => [$p->position_name, $p->has_ready_now ? 'Ya' : 'Tidak', (string) $p->candidate_count])->all(),
        ];

        return CsvExport::download('laporan-talenta-'.now()->format('Y-m-d').'.csv', ['Item', 'Nilai', 'Keterangan'], $rows);
    }

    /** @return Collection<int, stdClass> */
    private function popularCourses(): Collection
    {
        return DB::table('lms_enrollments as en')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->select('c.title', DB::raw('count(*) as jumlah'))
            ->groupBy('c.id', 'c.title')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, stdClass> */
    private function categoryDistribution(): Collection
    {
        return DB::table('lms_enrollments as en')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->select(DB::raw("coalesce(c.category, 'Tanpa Kategori') as kategori"), DB::raw('count(*) as jumlah'))
            ->groupBy('kategori')
            ->orderByDesc('jumlah')
            ->get();
    }

    /** @return Collection<int, stdClass> */
    private function trainingReportRows(): Collection
    {
        return DB::table('lms_course_batches as b')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->select(
                'b.id', 'c.title as course_title', 'b.batch_code', 'b.start_date', 'b.end_date',
                DB::raw('(select count(*) from lms_enrollments where batch_id = b.id) as terdaftar'),
                DB::raw("(select count(*) from lms_enrollments where batch_id = b.id and status = 'approved') as disetujui"),
                DB::raw("(select count(*) from lms_enrollments where batch_id = b.id and completion_status = 'lulus') as lulus"),
                DB::raw("(select count(*) from lms_enrollments where batch_id = b.id and completion_status = 'tidak_lulus') as tidak_lulus"),
            )
            ->orderByDesc('b.start_date')
            ->get()
            ->map(function ($row) {
                $selesai = $row->lulus + $row->tidak_lulus;
                $row->completion_rate = $selesai > 0 ? round(($row->lulus / $selesai) * 100, 1) : null;

                return $row;
            });
    }

    /** @return Collection<int, stdClass> */
    private function evaluationReportRows(): Collection
    {
        return DB::table('lms_assessments as a')
            ->select(
                'a.id', 'a.title', 'a.passing_score',
                DB::raw('(select count(*) from lms_assessment_attempts where assessment_id = a.id) as jumlah_attempt'),
                DB::raw("(select avg(total_score) from lms_assessment_attempts where assessment_id = a.id and status = 'scored') as rata_skor"),
                DB::raw("(select count(*) from lms_assessment_attempts where assessment_id = a.id and status = 'scored' and passed = true) as jumlah_lulus"),
                DB::raw("(select count(*) from lms_assessment_attempts where assessment_id = a.id and status = 'scored') as jumlah_ternilai"),
            )
            ->orderBy('a.title')
            ->get()
            ->map(function ($row) {
                $row->pass_rate = $row->jumlah_ternilai > 0 ? round(($row->jumlah_lulus / $row->jumlah_ternilai) * 100, 1) : null;

                return $row;
            });
    }

    /** @return Collection<int, stdClass> */
    private function competencyReportRows(): Collection
    {
        return DB::table('lms_position_competencies as pc')
            ->join('md_positions as p', 'p.id', '=', 'pc.position_id')
            ->join('lms_competencies as c', 'c.id', '=', 'pc.competency_id')
            ->select('p.name as position_name', 'c.name as competency_name', 'pc.required_level', 'pc.position_id', 'pc.competency_id')
            ->orderBy('p.name')
            ->orderBy('c.name')
            ->get()
            ->map(function ($row) {
                $avgCurrent = DB::table('lms_employee_competencies as ec')
                    ->join('emp_employees as e', 'e.id', '=', 'ec.employee_id')
                    ->where('e.position_id', $row->position_id)
                    ->where('ec.competency_id', $row->competency_id)
                    ->avg('ec.current_level');

                $row->avg_current_level = $avgCurrent !== null ? round((float) $avgCurrent, 1) : null;
                $row->avg_gap = $avgCurrent !== null ? round($row->required_level - $avgCurrent, 1) : null;

                return $row;
            });
    }

    /** @return Collection<int, stdClass> */
    private function talentKeyPositions(): Collection
    {
        return DB::table('lms_succession_plans as sp')
            ->join('md_positions as p', 'p.id', '=', 'sp.position_id')
            ->where('sp.is_active', true)
            ->select('sp.position_id', 'p.name as position_name')
            ->distinct()
            ->get()
            ->map(function ($row) {
                $row->has_ready_now = DB::table('lms_succession_plans')
                    ->where('position_id', $row->position_id)
                    ->where('is_active', true)
                    ->where('readiness_level', 'ready_now')
                    ->exists();

                $row->candidate_count = DB::table('lms_succession_plans')
                    ->where('position_id', $row->position_id)
                    ->where('is_active', true)
                    ->count();

                return $row;
            });
    }

    /** @return array<string, int> */
    private function talentReadinessDistribution(): array
    {
        $employeeIds = DB::table('emp_employees')->pluck('id');
        $distribution = ['rendah' => 0, 'sedang' => 0, 'tinggi' => 0, 'belum_dihitung' => 0];

        foreach ($employeeIds as $employeeId) {
            $score = $this->readiness->forEmployee($employeeId);

            $distribution[match (true) {
                $score === null => 'belum_dihitung',
                $score < 0.4 => 'rendah',
                $score < 0.7 => 'sedang',
                default => 'tinggi',
            }]++;
        }

        return $distribution;
    }

    /**
     * 5 kompetensi dengan gap organisasi-wide TERBESAR — dihitung per
     * baris dengan query sederhana (pola sama competencyReport()),
     * BUKAN join+subquery berkorelasi kompleks yang rawan salah pada
     * mesin basis data manapun.
     *
     * @return array<int, array{competency_name: string, position_name: string, avg_gap: float}>
     */
    private function topOrganizationWideGaps(): array
    {
        $mappings = DB::table('lms_position_competencies as pc')
            ->join('md_positions as p', 'p.id', '=', 'pc.position_id')
            ->join('lms_competencies as c', 'c.id', '=', 'pc.competency_id')
            ->select('p.name as position_name', 'c.name as competency_name', 'pc.required_level', 'pc.position_id', 'pc.competency_id')
            ->get();

        $gaps = [];

        foreach ($mappings as $m) {
            $avgCurrent = DB::table('lms_employee_competencies as ec')
                ->join('emp_employees as e', 'e.id', '=', 'ec.employee_id')
                ->where('e.position_id', $m->position_id)
                ->where('ec.competency_id', $m->competency_id)
                ->avg('ec.current_level');

            $avgCurrent = $avgCurrent !== null ? (float) $avgCurrent : 0.0;
            $gap = $m->required_level - $avgCurrent;

            if ($gap > 0) {
                $gaps[] = ['competency_name' => $m->competency_name, 'position_name' => $m->position_name, 'avg_gap' => round($gap, 1)];
            }
        }

        usort($gaps, fn ($a, $b) => $b['avg_gap'] <=> $a['avg_gap']);

        return array_slice($gaps, 0, 5);
    }
}
