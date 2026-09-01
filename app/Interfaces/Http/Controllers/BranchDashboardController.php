<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard dasar untuk hr_admin — lingkup OFFICE (kantor sendiri
 * saja), pola query SAMA seperti HcDashboardController TAPI SELALU
 * di-`where('office_id', $actor->officeId())`. Sengaja file TERPISAH
 * (bukan menumpangkan office-scoping ke HcDashboardController) — dashboard
 * HC secara eksplisit BANK_WIDE (ada test yang menegaskan itu), dan
 * cakupan di sini jauh lebih sempit (7 metrik, bukan agregat lengkap).
 */
final class BranchDashboardController extends Controller
{
    /** Jendela tampil daftar ulang tahun — SAMA seperti HcDashboardController, konsisten. */
    private const UPCOMING_WINDOW_MONTHS = 3;

    public function __construct(private readonly CurrentActor $actor) {}

    public function index(): View
    {
        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $office = DB::table('md_offices')->where('id', $officeId)->first();
        abort_if($office === null, 404);

        return view('hr.branch-dashboard', [
            'office' => $office,
            'totalPegawai' => (int) DB::table('emp_employees')->where('office_id', $officeId)->count(),
            'employmentStatusBreakdown' => $this->employmentStatusBreakdown($officeId),
            'genderBreakdown' => $this->genderBreakdown($officeId),
            'upcomingBirthdays' => $this->upcomingBirthdays($officeId),
            'upcomingWindowMonths' => self::UPCOMING_WINDOW_MONTHS,
        ]);
    }

    /** @return Collection<int, \stdClass> */
    private function employmentStatusBreakdown(string $officeId): Collection
    {
        return DB::table('emp_employees')
            ->where('office_id', $officeId)
            ->selectRaw('employment_status, count(*) as jumlah')
            ->groupBy('employment_status')
            ->orderBy('employment_status')
            ->get();
    }

    /** @return Collection<int, \stdClass> */
    private function genderBreakdown(string $officeId): Collection
    {
        return DB::table('emp_employees')
            ->where('office_id', $officeId)
            ->selectRaw('gender, count(*) as jumlah')
            ->groupBy('gender')
            ->get();
    }

    /**
     * Ulang tahun berulang TIAP TAHUN — cari kemunculan berikutnya
     * (tahun ini bila belum lewat, tahun depan bila sudah). Pola SAMA
     * HcDashboardController::upcomingBirthdays(), sengaja diduplikasi
     * (bukan diekstrak jadi trait) — cakupannya office-scoped, beda
     * query dasarnya, dan kecil (±15 baris), tidak sepadan menambah
     * lapisan abstraksi untuk dipakai di 2 tempat.
     *
     * @return Collection<int, object{full_name: string, nrp: string, tanggal: DateTimeImmutable}&\stdClass>
     */
    private function upcomingBirthdays(string $officeId): Collection
    {
        $today = new DateTimeImmutable('today');
        $windowEnd = $today->modify('+'.self::UPCOMING_WINDOW_MONTHS.' months');

        /** @var Collection<int, object{full_name: string, nrp: string, birth_date: string}> $rows */
        $rows = DB::table('emp_employees')
            ->where('office_id', $officeId)
            ->whereNotNull('birth_date')
            ->select('full_name', 'nrp', 'birth_date')
            ->get();

        return $rows
            ->map(fn ($e) => (object) [
                'full_name' => $e->full_name,
                'nrp' => $e->nrp,
                'tanggal' => $this->nextAnnualOccurrence(new DateTimeImmutable($e->birth_date), $today),
            ])
            ->filter(fn ($row) => $row->tanggal >= $today && $row->tanggal <= $windowEnd)
            ->sortBy('tanggal')
            ->values();
    }

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
}
