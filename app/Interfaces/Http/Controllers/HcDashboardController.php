<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Holiday\Domain\HolidayRepository;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard dasar untuk hr_approver — agregat BANK_WIDE (headcount,
 * kehadiran hari ini, dan pending approval lintas modul). Ini
 * "Dashboard dasar" TOR Fase I, BUKAN dashboard eksekutif penuh
 * dengan tren/grafik (itu "Dashboard lanjutan", Fase II).
 *
 * Seluruhnya hanya-baca — tidak ada aksi ubah di sini, murni
 * agregasi lintas modul lewat DB::table, mengikuti pola
 * ApprovalQueueController: fetch baris ter-scope, hitung ringkasan
 * in-memory. Bank-wide karena hr_approver TIDAK punya lingkup kantor
 * sendiri (ARCH-001 §6.2 — beda dengan hr_admin yang OFFICE).
 */
final class HcDashboardController extends Controller
{
    private const AGING_CRITICAL_DAYS = 3;

    private const AGING_WARNING_DAYS = 14;

    /** Jendela tampil untuk daftar ulang tahun/MPP/penghargaan masa bakti — dikonfirmasi bersama user. */
    private const UPCOMING_WINDOW_MONTHS = 3;

    /** MPP = sekian bulan SEBELUM usia pensiun normal (RETIREMENT_NORMAL_AGE) — dikonfirmasi bersama user. */
    private const MPP_LEAD_MONTHS = 6;

    /** Penghargaan Masa Bakti (PMB) — lihat komentar join_date di migrasi emp_employees. */
    private const SERVICE_AWARD_YEARS = [15, 20, 35];

    public function __construct(
        private readonly HolidayRepository $holidays,
        private readonly ParameterResolver $parameters,
    ) {}

    public function index(): View
    {
        $totalHeadcount = (int) DB::table('emp_employees')->count();

        $headcountByOffice = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->selectRaw('o.name as office_name, count(*) as jumlah')
            ->groupBy('o.name')
            ->orderBy('o.name')
            ->get();

        $headcountByStatus = DB::table('emp_employees')
            ->selectRaw('employment_status, count(*) as jumlah')
            ->groupBy('employment_status')
            ->get();

        $today = now()->format('Y-m-d');

        $hadirHariIni = (int) DB::table('att_attendance_records')
            ->where('work_date', $today)->where('status', 'hadir')->count();
        $telatHariIni = (int) DB::table('att_attendance_records')
            ->where('work_date', $today)->where('status', 'telat')->count();

        // Proksi kasar (headcount total - hadir - telat) — mengasumsikan
        // SELURUH pegawai dijadwalkan masuk hari ini, tidak mengecualikan
        // pegawai yang sedang cuti/off (itu tetap tidak dikecualikan).
        // Kalau HARI INI sendiri akhir pekan/hari libur nasional, angka
        // ini langsung 0 — rumus total-hadir-telat menyesatkan pada
        // hari yang memang tidak ada yang dijadwalkan masuk.
        $today0 = new DateTimeImmutable($today);
        $isNonWorkingDay = (int) $today0->format('N') >= 6 || $this->holidays->isHoliday($today0);
        $tanpaCatatanHariIni = $isNonWorkingDay ? 0 : max(0, $totalHeadcount - $hadirHariIni - $telatHariIni);

        $pendingApprovals = [
            'cuti' => (int) DB::table('leave_requests')->where('status', 'pending')->count(),
            'lembur' => (int) DB::table('ovt_requests')->where('status', 'pending')->count(),
            'sppd' => (int) DB::table('spd_requests')->where('status', 'pending')->count(),
            'payroll' => (int) DB::table('pay_payroll_runs')->where('status', 'draft')->count(),
            'data_pegawai' => (int) DB::table('emp_profile_change_requests')->where('status', 'pending')->count(),
            'tukar_shift' => (int) DB::table('shf_swap_requests')->where('status', 'pending')->count(),
        ];

        $recentActivity = DB::table('aud_change_logs as a')
            ->leftJoin('emp_employees as e', 'e.id', '=', 'a.actor_id')
            ->select(
                'a.occurred_at', 'a.actor_role', 'a.auditable_type', 'a.action', 'a.context_ref',
                'e.full_name as actor_name', 'e.nrp as actor_nrp'
            )
            ->orderByDesc('a.occurred_at')
            ->limit(15)
            ->get();

        return view('hc.dashboard', [
            'totalHeadcount' => $totalHeadcount,
            'headcountByOffice' => $headcountByOffice,
            'headcountByStatus' => $headcountByStatus,
            'headcountByGrade' => $this->headcountByGrade(),
            'gapByOffice' => $this->gapByOffice(),
            'hadirHariIni' => $hadirHariIni,
            'telatHariIni' => $telatHariIni,
            'tanpaCatatanHariIni' => $tanpaCatatanHariIni,
            'pendingApprovals' => $pendingApprovals,
            'pendingApprovalsAging' => $this->pendingApprovalsAging(),
            'recentActivity' => $recentActivity,
            'employmentStatusBreakdown' => $this->employmentStatusBreakdown(),
            'genderBreakdown' => $this->genderBreakdown(),
            'upcomingBirthdays' => $this->upcomingBirthdays(),
            'upcomingMpp' => $this->upcomingMpp(),
            'upcomingServiceAwards' => $this->upcomingServiceAwards(),
            'headcountByBranchOffice' => $this->headcountByBranchOffice(),
            'positionDistributionByBranchOffice' => $this->positionDistributionByBranchOffice(),
            'upcomingWindowMonths' => self::UPCOMING_WINDOW_MONTHS,
        ]);
    }

    /** @return Collection<int, \stdClass> */
    private function headcountByGrade(): Collection
    {
        return DB::table('emp_employees')
            ->selectRaw('person_grade, count(*) as jumlah')
            ->whereNotNull('person_grade')
            ->groupBy('person_grade')
            ->orderBy('person_grade')
            ->get();
    }

    /** @return Collection<int, \stdClass> GAP = formasi − aktual; kantor tanpa formasi ditandai "belum ditetapkan" di view. */
    private function gapByOffice(): Collection
    {
        return DB::table('md_offices as o')
            ->leftJoin('emp_employees as e', 'e.office_id', '=', 'o.id')
            ->selectRaw('o.name as office_name, o.authorized_headcount, count(e.id) as actual_headcount')
            ->groupBy('o.id', 'o.name', 'o.authorized_headcount')
            ->orderBy('o.name')
            ->get();
    }

    /**
     * Umur (hari sejak diajukan) tiap antrean pending, dikelompokkan
     * pakai ambang yang SAMA dengan urgensi tenggat SLA di
     * LeaveApprovalQueueController (CRITICAL_DAYS=3, WARNING_DAYS=14) —
     * angka yang sama dipakai ulang murni untuk konsistensi visual di
     * seluruh aplikasi, walau konsepnya beda ("umur sejak diajukan"
     * di sini, bukan "sisa waktu sampai tenggat").
     *
     * @return array<string, array{aman: int, hati: int, kritis: int}>
     */
    private function pendingApprovalsAging(): array
    {
        $queues = [
            'cuti' => 'leave_requests',
            'lembur' => 'ovt_requests',
            'sppd' => 'spd_requests',
            'data_pegawai' => 'emp_profile_change_requests',
            'tukar_shift' => 'shf_swap_requests',
        ];

        $result = [];

        foreach ($queues as $label => $table) {
            $rows = DB::table($table)->where('status', 'pending')->pluck('created_at');

            $buckets = ['aman' => 0, 'hati' => 0, 'kritis' => 0];

            foreach ($rows as $createdAt) {
                $ageDays = (new DateTimeImmutable((string) $createdAt))->diff(new DateTimeImmutable)->days;

                $buckets[match (true) {
                    $ageDays >= self::AGING_WARNING_DAYS => 'kritis',
                    $ageDays >= self::AGING_CRITICAL_DAYS => 'hati',
                    default => 'aman',
                }]++;
            }

            $result[$label] = $buckets;
        }

        return $result;
    }

    /** @return Collection<int, \stdClass> */
    private function employmentStatusBreakdown(): Collection
    {
        return DB::table('emp_employees')
            ->selectRaw('employment_status, count(*) as jumlah')
            ->groupBy('employment_status')
            ->orderBy('employment_status')
            ->get();
    }

    /** @return Collection<int, \stdClass> */
    private function genderBreakdown(): Collection
    {
        return DB::table('emp_employees')
            ->selectRaw('gender, count(*) as jumlah')
            ->groupBy('gender')
            ->get();
    }

    /**
     * Pegawai yang berulang tahun dalam UPCOMING_WINDOW_MONTHS ke depan —
     * ulang tahun berulang TIAP TAHUN (beda dari MPP/penghargaan masa
     * bakti yang satu kali saja), jadi perlu cari kemunculan BERIKUTNYA
     * (tahun ini bila belum lewat, tahun depan bila sudah).
     *
     * @return Collection<int, object{full_name: string, nrp: string, office_name: string, tanggal: DateTimeImmutable}&\stdClass>
     */
    private function upcomingBirthdays(): Collection
    {
        $today = new DateTimeImmutable('today');
        $windowEnd = $today->modify('+'.self::UPCOMING_WINDOW_MONTHS.' months');

        /** @var Collection<int, object{full_name: string, nrp: string, birth_date: string, office_name: string}> $rows */
        $rows = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->whereNotNull('e.birth_date')
            ->select('e.full_name', 'e.nrp', 'e.birth_date', 'o.name as office_name')
            ->get();

        return $rows
            ->map(fn ($e) => (object) [
                'full_name' => $e->full_name,
                'nrp' => $e->nrp,
                'office_name' => $e->office_name,
                'tanggal' => $this->nextAnnualOccurrence(new DateTimeImmutable($e->birth_date), $today),
            ])
            ->filter(fn ($row) => $row->tanggal >= $today && $row->tanggal <= $windowEnd)
            ->sortBy('tanggal')
            ->values();
    }

    /**
     * Pegawai yang akan memasuki MPP (Masa Persiapan Pensiun) dalam
     * UPCOMING_WINDOW_MONTHS ke depan — MPP_LEAD_MONTHS sebelum usia
     * pensiun normal (parameter RETIREMENT_NORMAL_AGE, SEBELUMNYA
     * tersimpan tapi tidak pernah dipakai kode manapun).
     *
     * @return Collection<int, object{full_name: string, nrp: string, office_name: string, tanggal: DateTimeImmutable}&\stdClass>
     */
    private function upcomingMpp(): Collection
    {
        $today = new DateTimeImmutable('today');
        $windowEnd = $today->modify('+'.self::UPCOMING_WINDOW_MONTHS.' months');
        $retirementAge = $this->parameters->integer('RETIREMENT_NORMAL_AGE', AsOfDate::on($today));

        /** @var Collection<int, object{full_name: string, nrp: string, birth_date: string, office_name: string}> $rows */
        $rows = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->whereNotNull('e.birth_date')
            ->select('e.full_name', 'e.nrp', 'e.birth_date', 'o.name as office_name')
            ->get();

        return $rows
            ->map(fn ($e) => (object) [
                'full_name' => $e->full_name,
                'nrp' => $e->nrp,
                'office_name' => $e->office_name,
                'tanggal' => (new DateTimeImmutable($e->birth_date))
                    ->modify("+{$retirementAge} years")
                    ->modify('-'.self::MPP_LEAD_MONTHS.' months'),
            ])
            ->filter(fn ($row) => $row->tanggal >= $today && $row->tanggal <= $windowEnd)
            ->sortBy('tanggal')
            ->values();
    }

    /**
     * Pegawai yang akan mencapai milestone Penghargaan Masa Bakti (PMB)
     * dalam UPCOMING_WINDOW_MONTHS ke depan, dikelompokkan per tahun
     * milestone — berbasis join_date (Masa Kerja Riil), BUKAN
     * permanent_date (lihat komentar kolom di migrasi emp_employees).
     *
     * @return array<int, Collection<int, object{full_name: string, nrp: string, office_name: string, tanggal: DateTimeImmutable}&\stdClass>> dikunci tahun milestone (15/20/35)
     */
    private function upcomingServiceAwards(): array
    {
        $today = new DateTimeImmutable('today');
        $windowEnd = $today->modify('+'.self::UPCOMING_WINDOW_MONTHS.' months');

        /** @var Collection<int, object{full_name: string, nrp: string, join_date: string, office_name: string}> $employees */
        $employees = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->whereNotNull('e.join_date')
            ->select('e.full_name', 'e.nrp', 'e.join_date', 'o.name as office_name')
            ->get();

        $result = [];

        foreach (self::SERVICE_AWARD_YEARS as $years) {
            $result[$years] = $employees
                ->map(fn ($e) => (object) [
                    'full_name' => $e->full_name,
                    'nrp' => $e->nrp,
                    'office_name' => $e->office_name,
                    'tanggal' => (new DateTimeImmutable($e->join_date))->modify("+{$years} years"),
                ])
                ->filter(fn ($row) => $row->tanggal >= $today && $row->tanggal <= $windowEnd)
                ->sortBy('tanggal')
                ->values();
        }

        return $result;
    }

    /** Kemunculan tahunan BERIKUTNYA dari tanggal $anniversary relatif $today — 29 Feb pada tahun bukan kabisat jatuh ke 28 Feb (bukan meluber ke Maret). */
    private function nextAnnualOccurrence(DateTimeImmutable $anniversary, DateTimeImmutable $today): DateTimeImmutable
    {
        $month = (int) $anniversary->format('n');
        $day = (int) $anniversary->format('j');
        $year = (int) $today->format('Y');

        $thisYear = $this->clampedDate($year, $month, $day);

        return $thisYear >= $today ? $thisYear : $this->clampedDate($year + 1, $month, $day);
    }

    private function clampedDate(int $year, int $month, int $day): DateTimeImmutable
    {
        $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, min($day, $daysInMonth)));
    }

    /**
     * "Daftar Jumlah pegawai pada masing-masing KC dan KCP" — subset headcountByOffice, hanya branch/sub_branch.
     *
     * @return Collection<int, \stdClass>
     */
    private function headcountByBranchOffice(): Collection
    {
        return DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->whereIn('o.office_type', ['branch', 'sub_branch'])
            ->selectRaw('o.name as office_name, o.office_type, count(*) as jumlah')
            ->groupBy('o.id', 'o.name', 'o.office_type')
            ->orderBy('o.name')
            ->get();
    }

    /**
     * "Persebaran Data Per masing-masing jabatan pada KC dan KCP" — baris datar, dikelompokkan per kantor di view.
     *
     * @return Collection<int, \stdClass>
     */
    private function positionDistributionByBranchOffice(): Collection
    {
        return DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->whereIn('o.office_type', ['branch', 'sub_branch'])
            ->selectRaw('o.name as office_name, p.name as position_name, count(*) as jumlah')
            ->groupBy('o.id', 'o.name', 'p.id', 'p.name')
            ->orderBy('o.name')
            ->orderBy('p.name')
            ->get();
    }
}
