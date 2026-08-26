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
 * Dua tampilan: harian (200 baris terbaru, perilaku asli) dan
 * bulanan (agregat per pegawai — total hadir/telat, dan "hari tanpa
 * catatan" sebagai monitoring keterlambatan/ketidakhadiran, TOR Fase
 * I baris Absensi).
 *
 * @phpstan-type DailyRow object{
 *   work_date: string, check_in_at: ?string, check_in_source: ?string,
 *   break_start_at: ?string, break_end_at: ?string,
 *   check_out_at: ?string, check_out_source: ?string, status: string,
 *   full_name: string, nrp: string, office_name: string, office_type: string,
 *   office_timezone: string,
 * }
 * @phpstan-type MonthlyRow object{
 *   full_name: string, nrp: string, office_name: string, office_type: string,
 *   total_hadir: int, total_telat: int, hari_tanpa_catatan: int,
 * }
 */
final class AttendanceRecapController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly HolidayRepository $holidays,
    ) {}

    public function index(Request $request): View
    {
        [$officeId, $office] = $this->resolveScope();
        $officeType = $officeId === null ? $this->officeTypeFilter($request) : null;

        if ($request->string('tampilan')->toString() === 'bulanan') {
            $bulan = $request->string('bulan')->toString() ?: now()->format('Y-m');

            return view('admin.attendance-recap', [
                'office' => $office, 'tampilan' => 'bulanan', 'bulan' => $bulan,
                'workingDays' => $this->countWorkingDays($bulan),
                'perPegawai' => $this->monthlyPerPegawai($officeId, $officeType, $bulan),
                'officeType' => $officeType,
            ]);
        }

        return view('admin.attendance-recap', [
            'office' => $office, 'rows' => $this->dailyRows($officeId, $officeType),
            'tampilan' => 'harian', 'officeType' => $officeType,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$officeId] = $this->resolveScope();
        $officeType = $officeId === null ? $this->officeTypeFilter($request) : null;
        $isBankWide = $officeId === null;

        if ($request->string('tampilan')->toString() === 'bulanan') {
            $bulan = $request->string('bulan')->toString() ?: now()->format('Y-m');
            $perPegawai = $this->monthlyPerPegawai($officeId, $officeType, $bulan);

            $headers = ['Nama Pegawai', 'NRP'];
            if ($isBankWide) {
                $headers[] = 'Kantor';
            }
            array_push($headers, 'Hadir', 'Terlambat', 'Tanpa Catatan');

            return CsvExport::download(
                "rekap-absensi-bulanan-{$bulan}.csv",
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

        $rows = $this->dailyRows($officeId, $officeType);
        $labelSumber = ['gps' => 'GPS', 'fingerprint' => 'Fingerprint', 'luar_kantor' => 'Luar Kantor'];
        $labelStatus = ['hadir' => 'Hadir', 'telat' => 'Terlambat', 'absen' => 'Tidak Hadir'];

        $headers = ['Tanggal', 'Nama Pegawai', 'NRP'];
        if ($isBankWide) {
            $headers[] = 'Kantor';
        }
        array_push($headers, 'Jam Masuk', 'Sumber Masuk', 'Istirahat', 'Kembali', 'Jam Pulang', 'Sumber Pulang', 'Status');

        return CsvExport::download(
            'rekap-absensi-harian.csv',
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

    /** @return Collection<int, stdClass> */
    private function dailyRows(?string $officeId, ?string $officeType)
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
            ->orderByDesc('a.work_date')
            ->orderBy('e.full_name')
            ->limit(200)
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
    private function monthlyPerPegawai(?string $officeId, ?string $officeType, string $bulan): Collection
    {
        $rows = $this->scoped(
            DB::table('att_attendance_records as a')
                ->join('emp_employees as e', 'e.id', '=', 'a.employee_id')
                ->join('md_offices as o', 'o.id', '=', 'e.office_id')
                ->select('e.id as employee_id', 'e.full_name', 'e.nrp', 'o.name as office_name', 'o.office_type', 'a.status'),
            $officeId,
            $officeType,
        )
            ->whereRaw("to_char(a.work_date, 'YYYY-MM') = ?", [$bulan])
            ->get();

        $workingDays = $this->countWorkingDays($bulan);

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

    /** Hari kerja dalam bulan — mengecualikan akhir pekan & hari libur nasional. */
    private function countWorkingDays(string $yearMonth): int
    {
        $start = new DateTimeImmutable("{$yearMonth}-01");
        $end = $start->modify('last day of this month');

        return $this->holidays->countWorkingDays($start, $end);
    }
}
