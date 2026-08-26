<?php

declare(strict_types=1);

namespace App\Shared\Holiday\Infrastructure;

use App\Shared\Holiday\Domain\Holiday;
use App\Shared\Holiday\Domain\HolidayRepository;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Implementasi kalender libur dari cfg_national_holidays.
 *
 * Baris di-cache PER TAHUN sebagai objek Holiday (Domain) yang sudah
 * jadi, BUKAN stdClass mentah — supaya properti dapat diakses lintas
 * method tanpa terjebak celah PHPStan pada return type `object` bentuk
 * bebas (lihat catatan serupa di EloquentAuditRepository/
 * DatabaseParameterResolver). Kalender libur jarang berubah tapi dibaca
 * di setiap pengajuan cuti & setiap buka rekap absensi, sama semangat
 * DatabaseParameterResolver.
 */
final class EloquentHolidayRepository implements HolidayRepository
{
    private const CACHE_TTL_SECONDS = 3600;

    public function isHoliday(DateTimeImmutable $date): bool
    {
        return array_key_exists($date->format('Y-m-d'), $this->holidaysForYear((int) $date->format('Y')));
    }

    public function between(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $holidays = [];

        foreach ($this->holidaysForYearRange($start, $end) as $holiday) {
            if ($holiday->date >= $start && $holiday->date <= $end) {
                $holidays[] = $holiday;
            }
        }

        return $holidays;
    }

    public function countWorkingDays(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $holidayDates = $this->holidaysForYearRange($start, $end);

        $count = 0;
        $cursor = $start;

        while ($cursor <= $end) {
            $isWeekend = (int) $cursor->format('N') >= 6;
            $isHoliday = array_key_exists($cursor->format('Y-m-d'), $holidayDates);

            if (! $isWeekend && ! $isHoliday) {
                $count++;
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $count;
    }

    /** @return array<string, Holiday> keyed by Y-m-d, mencakup seluruh tahun dari $start s.d. $end */
    private function holidaysForYearRange(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $holidays = [];

        for ($year = (int) $start->format('Y'); $year <= (int) $end->format('Y'); $year++) {
            $holidays = [...$holidays, ...$this->holidaysForYear($year)];
        }

        return $holidays;
    }

    /** @return array<string, Holiday> keyed by Y-m-d */
    private function holidaysForYear(int $year): array
    {
        return Cache::remember(
            "holiday:rows:{$year}",
            self::CACHE_TTL_SECONDS,
            function () use ($year) {
                $rows = DB::table('cfg_national_holidays')
                    ->whereYear('holiday_date', $year)
                    ->select('id', 'holiday_date', 'name', 'is_national')
                    ->get();

                $keyed = [];
                foreach ($rows as $row) {
                    $date = new DateTimeImmutable((string) $row->holiday_date);
                    $keyed[$date->format('Y-m-d')] = new Holiday(
                        id: (string) $row->id,
                        date: $date,
                        name: (string) $row->name,
                        isNational: (bool) $row->is_national,
                    );
                }

                return $keyed;
            },
        );
    }
}
