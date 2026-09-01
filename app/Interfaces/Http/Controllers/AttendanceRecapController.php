<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\CsvExport;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Shared\Holiday\Domain\HolidayRepository;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap absensi — hr_admin melihat kantornya sendiri (lingkup OFFICE,
 * ARCH-001 §6.2, sama seperti Data Pegawai), hr_approver melihat
 * seluruh bank (lingkup BANK_WIDE — kantor pusat seluruh divisi +
 * seluruh kantor cabang + kantor cabang pembantu), pola SAMA seperti
 * OvertimeRecapController::scopedRows(). Hanya-baca.
 *
 * Lima tampilan: harian (log mentah SATU tanggal — jam masuk/pulang
 * per pegawai) dan mingguan/bulanan/tahunan/rentang (agregat per
 * pegawai atas rentang tanggal — total hadir/telat, dan "hari tanpa
 * catatan" sebagai monitoring keterlambatan/ketidakhadiran, TOR Fase
 * I baris Absensi). Keempat tampilan agregat BEDA hanya pada cara
 * [start, end] dihitung dari parameter query (lihat resolveRange()) —
 * query & tabelnya sama persis (perPegawaiForRange()).
 *
 * @phpstan-type DailyRow object{
 *   work_date: string, check_in_at: ?string, check_in_source: ?string,
 *   break_start_at: ?string, break_end_at: ?string,
 *   check_out_at: ?string, check_out_source: ?string, status: string,
 *   full_name: string, nrp: string, office_name: string, office_type: string,
 *   office_timezone: string,
 * }
 * @phpstan-type AgregatRow object{
 *   full_name: string, nrp: string, office_name: string, office_type: string,
 *   total_hadir: int, total_telat: int, hari_tanpa_catatan: int,
 * }
 */
final class AttendanceRecapController extends Controller
{
    private const TAMPILAN_VALID = ['harian', 'mingguan', 'bulanan', 'tahunan', 'rentang'];

    public function __construct(
        private readonly CurrentActor $actor,
        private readonly HolidayRepository $holidays,
    ) {}

    public function index(Request $request): View
    {
        [$officeId, $office] = $this->resolveScope();
        $officeType = $officeId === null ? $this->officeTypeFilter($request) : null;
        $tampilan = $this->tampilanFilter($request);

        if ($tampilan === 'harian') {
            $tanggal = $this->tanggalFilter($request);

            return view('admin.attendance-recap', [
                'office' => $office, 'tampilan' => 'harian', 'tanggal' => $tanggal,
                'rows' => $this->dailyRows($officeId, $officeType, $tanggal),
                'officeType' => $officeType,
            ]);
        }

        [$start, $end, $periodeParams] = $this->resolveRange($tampilan, $request);

        return view('admin.attendance-recap', array_merge([
            'office' => $office, 'tampilan' => $tampilan,
            'workingDays' => $this->holidays->countWorkingDays($start, $end),
            'perPegawai' => $this->perPegawaiForRange($officeId, $officeType, $start, $end),
            'officeType' => $officeType,
        ], $periodeParams));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$officeId] = $this->resolveScope();
        $officeType = $officeId === null ? $this->officeTypeFilter($request) : null;
        $isBankWide = $officeId === null;
        $tampilan = $this->tampilanFilter($request);

        if ($tampilan !== 'harian') {
            [$start, $end, $periodeParams] = $this->resolveRange($tampilan, $request);
            $perPegawai = $this->perPegawaiForRange($officeId, $officeType, $start, $end);

            $headers = ['Nama Pegawai', 'NRP'];
            if ($isBankWide) {
                $headers[] = 'Kantor';
            }
            array_push($headers, 'Hadir', 'Terlambat', 'Tanpa Catatan');

            return CsvExport::download(
                "rekap-absensi-{$tampilan}-{$this->periodeSlug($tampilan, $periodeParams)}.csv",
                $headers,
                $perPegawai->map(function ($p) use ($isBankWide) {
                    $row = [$p->full_name, $p->nrp];
                    if ($isBankWide) {
                        $row[] = $p->office_name;
                    }
                    array_push($row, $p->total_hadir, $p->total_telat, $p->hari_tanpa_catatan);

                    return $row;
                })->all(),
            );
        }

        $tanggal = $this->tanggalFilter($request);
        $rows = $this->dailyRows($officeId, $officeType, $tanggal);
        $labelSumber = ['gps' => 'GPS', 'fingerprint' => 'Fingerprint', 'luar_kantor' => 'Luar Kantor'];
        $labelStatus = ['hadir' => 'Hadir', 'telat' => 'Terlambat', 'absen' => 'Tidak Hadir'];

        $headers = ['Tanggal', 'Nama Pegawai', 'NRP'];
        if ($isBankWide) {
            $headers[] = 'Kantor';
        }
        array_push($headers, 'Jam Masuk', 'Sumber Masuk', 'Istirahat', 'Kembali', 'Jam Pulang', 'Sumber Pulang', 'Status');

        return CsvExport::download(
            "rekap-absensi-harian-{$tanggal}.csv",
            $headers,
            $rows->map(function ($r) use ($isBankWide, $labelSumber, $labelStatus) {
                $row = [$r->work_date, $r->full_name, $r->nrp];
                if ($isBankWide) {
                    $row[] = $r->office_name;
                }
                array_push(
                    $row,
                    $r->check_in_local ?? '',
                    $labelSumber[$r->check_in_source] ?? $r->check_in_source ?? '',
                    $r->break_start_local ?? '',
                    $r->break_end_local ?? '',
                    $r->check_out_local ?? '',
                    $labelSumber[$r->check_out_source] ?? $r->check_out_source ?? '',
                    $labelStatus[$r->status] ?? $r->status,
                );

                return $row;
            })->all(),
        );
    }

    /**
     * @return array{0: ?string, 1: ?object} [officeId, office] — officeId
     *                                       null berarti BANK_WIDE (hr_approver).
     */
    private function resolveScope(): array
    {
        if (! $this->actor->hasRole(Role::HrAdmin->value)) {
            // hr_approver — gerbang permission attendance-recap.view sudah
            // menyaring peran lain; BANK_WIDE, tidak dibatasi kantor.
            return [null, null];
        }

        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $office = DB::table('md_offices')->where('id', $officeId)->first();
        abort_if($office === null, 404);

        return [$officeId, $office];
    }

    private function officeTypeFilter(Request $request): ?string
    {
        $value = $request->string('tipe_kantor')->toString();

        return in_array($value, ['head_office', 'branch', 'sub_branch'], true) ? $value : null;
    }

    private function tampilanFilter(Request $request): string
    {
        $value = $request->string('tampilan')->toString();

        return in_array($value, self::TAMPILAN_VALID, true) ? $value : 'harian';
    }

    /** Format Y-m-d, tervalidasi (default hari ini bila kosong/tidak valid). */
    private function tanggalFilter(Request $request): string
    {
        $value = $request->string('tanggal')->toString();
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false ? $parsed->format('Y-m-d') : now()->format('Y-m-d');
    }

    /**
     * Menghitung [start, end] dari parameter query sesuai jenis tampilan,
     * plus parameter yang perlu digemakan balik ke view (nilai input yang
     * SEDANG aktif, dipakai form filter + tautan Ekspor CSV) — SATU
     * tempat ini dipakai index() maupun exportCsv() supaya rentang yang
     * dihitung selalu identik antara tampilan layar dan hasil unduhan.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: array<string, string>}
     */
    private function resolveRange(string $tampilan, Request $request): array
    {
        return match ($tampilan) {
            'mingguan' => $this->resolveWeekRange($request),
            'tahunan' => $this->resolveYearRange($request),
            'rentang' => $this->resolveCustomRange($request),
            default => $this->resolveMonthRange($request), // 'bulanan'
        };
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: array{bulan: string}} */
    private function resolveMonthRange(Request $request): array
    {
        $value = $request->string('bulan')->toString();
        $start = DateTimeImmutable::createFromFormat('!Y-m', $value) ?: new DateTimeImmutable('first day of this month');
        $end = $start->modify('last day of this month');

        return [$start, $end, ['bulan' => $start->format('Y-m')]];
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: array{minggu: string}} */
    private function resolveWeekRange(Request $request): array
    {
        $value = $request->string('minggu')->toString();

        if (preg_match('/^(\d{4})-W(\d{2})$/', $value, $m) === 1) {
            $start = (new DateTimeImmutable)->setISODate((int) $m[1], (int) $m[2], 1);
        } else {
            $now = new DateTimeImmutable;
            $start = $now->setISODate((int) $now->format('o'), (int) $now->format('W'), 1);
        }

        $end = $start->modify('+6 days');

        return [$start, $end, ['minggu' => $start->format('o').'-W'.$start->format('W')]];
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: array{tahun: string}} */
    private function resolveYearRange(Request $request): array
    {
        $value = $request->integer('tahun');
        $year = $value >= 2000 && $value <= 2100 ? $value : (int) now()->format('Y');
        $start = new DateTimeImmutable("{$year}-01-01");
        $end = new DateTimeImmutable("{$year}-12-31");

        return [$start, $end, ['tahun' => (string) $year]];
    }

    /**
     * Rentang bebas — bila "sampai" sebelum "dari", ditukar (defensif,
     * daripada query kosong/error tanpa penjelasan). Default: bulan
     * berjalan (dari tanggal 1 s.d. hari ini), sama seperti bulanan
     * tanpa parameter.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: array{dari: string, sampai: string}}
     */
    private function resolveCustomRange(Request $request): array
    {
        $dariRaw = DateTimeImmutable::createFromFormat('!Y-m-d', $request->string('dari')->toString());
        $sampaiRaw = DateTimeImmutable::createFromFormat('!Y-m-d', $request->string('sampai')->toString());

        $dari = $dariRaw ?: new DateTimeImmutable('first day of this month');
        $sampai = $sampaiRaw ?: new DateTimeImmutable('today');

        if ($dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [$dari, $sampai, ['dari' => $dari->format('Y-m-d'), 'sampai' => $sampai->format('Y-m-d')]];
    }

    /** @param array<string, string> $periodeParams */
    private function periodeSlug(string $tampilan, array $periodeParams): string
    {
        return match ($tampilan) {
            'mingguan' => $periodeParams['minggu'],
            'tahunan' => $periodeParams['tahun'],
            'rentang' => $periodeParams['dari'].'_'.$periodeParams['sampai'],
            default => $periodeParams['bulan'],
        };
    }

    /** @return Collection<int, stdClass> */
    private function dailyRows(?string $officeId, ?string $officeType, string $tanggal)
    {
        $rows = $this->scoped(
            DB::table('att_attendance_records as a')
                ->join('emp_employees as e', 'e.id', '=', 'a.employee_id')
                ->join('md_offices as o', 'o.id', '=', 'e.office_id')
                ->select(
                    'a.work_date', 'a.check_in_at', 'a.check_in_source',
                    'a.break_start_at', 'a.break_end_at',
                    'a.check_out_at', 'a.check_out_source', 'a.status',
                    'e.full_name', 'e.nrp', 'o.name as office_name', 'o.office_type', 'o.timezone as office_timezone',
                ),
            $officeId,
            $officeType,
        )
            ->where('a.work_date', $tanggal)
            ->orderBy('e.full_name')
            ->get();

        // Bug ditemukan lewat audit kode: check_in_at/check_out_at adalah
        // timestamptz (disimpan UTC, benar), tapi date('H:i', strtotime(...))
        // di view/CSV lama SELALU memformat pakai timezone default PHP
        // (UTC, config/app.php) — mengabaikan zona kantor sepenuhnya. Untuk
        // kantor WITA/WIT, jam masuk/pulang yang ditampilkan ke HR/pegawai
        // salah beberapa jam (mis. masuk 08:00 WITA tertulis "00:00").
        // Pola konversi yang benar SUDAH ada di EloquentAttendanceRepository
        // (setTimezone($officeTz) sebelum format) — diterapkan di sini
        // dengan menyiapkan string "H:i" siap-pakai per baris, supaya view
        // dan ekspor CSV cukup mencetak apa adanya tanpa strtotime() ulang
        // (yang akan membuang info zona waktu ini lagi).
        return $rows->map(function ($r) {
            $tz = new \DateTimeZone($r->office_timezone);
            $format = fn (?string $iso) => $iso ? (new DateTimeImmutable($iso))->setTimezone($tz)->format('H:i') : null;

            $r->check_in_local = $format($r->check_in_at);
            $r->break_start_local = $format($r->break_start_at);
            $r->break_end_local = $format($r->break_end_at);
            $r->check_out_local = $format($r->check_out_at);

            return $r;
        });
    }

    /** @return Collection<int, object{full_name: string, nrp: string, office_name: string, office_type: string, total_hadir: int, total_telat: int, hari_tanpa_catatan: int}> */
    private function perPegawaiForRange(?string $officeId, ?string $officeType, DateTimeImmutable $start, DateTimeImmutable $end): Collection
    {
        $rows = $this->scoped(
            DB::table('att_attendance_records as a')
                ->join('emp_employees as e', 'e.id', '=', 'a.employee_id')
                ->join('md_offices as o', 'o.id', '=', 'e.office_id')
                ->select('e.id as employee_id', 'e.full_name', 'e.nrp', 'o.name as office_name', 'o.office_type', 'a.status'),
            $officeId,
            $officeType,
        )
            ->whereBetween('a.work_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        $workingDays = $this->holidays->countWorkingDays($start, $end);

        $perPegawai = $rows->groupBy('employee_id')->map(function ($group) use ($workingDays) {
            /** @var object{full_name: string, nrp: string, office_name: string, office_type: string} $first */
            $first = $group->firstOrFail();
            $hadir = $group->where('status', 'hadir')->count();
            $telat = $group->where('status', 'telat')->count();

            return (object) [
                'full_name' => $first->full_name,
                'nrp' => $first->nrp,
                'office_name' => $first->office_name,
                'office_type' => $first->office_type,
                'total_hadir' => $hadir,
                'total_telat' => $telat,
                // Masih proksi — status 'absen' tidak pernah ditulis
                // kode manapun (lihat catatan Domain
                // AttendanceDayPolicy), jadi hari tanpa catatan tidak
                // terlihat sebagai baris 'absen', hanya selisih di sini.
                // $workingDays sekarang SUDAH mengecualikan akhir pekan
                // & hari libur nasional (HolidayRepository), tapi
                // cuti/off individual per pegawai tetap tidak dikecualikan.
                'hari_tanpa_catatan' => max(0, $workingDays - $hadir - $telat),
            ];
        });

        /** @var Collection<int, object{full_name: string, nrp: string, office_name: string, office_type: string, total_hadir: int, total_telat: int, hari_tanpa_catatan: int}> $sorted */
        $sorted = $perPegawai->sortBy('full_name')->values();

        return $sorted;
    }

    /** officeId != null → satu kantor (hr_admin). officeId == null → BANK_WIDE, officeType opsional menyaring kategori (hr_approver). */
    private function scoped(Builder $query, ?string $officeId, ?string $officeType): Builder
    {
        if ($officeId !== null) {
            return $query->where('e.office_id', $officeId);
        }

        if ($officeType !== null) {
            return $query->where('o.office_type', $officeType);
        }

        return $query;
    }
}
