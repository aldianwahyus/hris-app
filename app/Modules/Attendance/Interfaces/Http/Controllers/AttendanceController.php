<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Http\Controllers;

use App\Modules\Attendance\Application\RecordGpsAttendance;
use App\Modules\Attendance\Application\RequestOutsideAttendance;
use App\Modules\Attendance\Domain\AlreadyCompletedToday;
use App\Modules\Attendance\Domain\AttendanceAction;
use App\Modules\Attendance\Domain\AttendanceActionNotAllowed;
use App\Modules\Attendance\Domain\AttendanceStatus;
use App\Modules\Attendance\Domain\BreakNotYetAllowed;
use App\Modules\Attendance\Domain\GeoCoordinate;
use App\Modules\Attendance\Domain\OutsideGeofence;
use App\Modules\Attendance\Interfaces\Http\Requests\RecordGpsAttendanceForm;
use App\Modules\Attendance\Interfaces\Http\Requests\RequestOutsideAttendanceForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Absen GPS dari ESS — SELALU atas nama pegawai yang sedang masuk
 * (ownership, lingkup SELF, tidak ada parameter employee_id di sini).
 */
final class AttendanceController
{
    public function __construct(
        private readonly RecordGpsAttendance $record,
        private readonly RequestOutsideAttendance $requestOutside,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        // Bug ditemukan lewat audit kode: check_in_at/check_out_at
        // timestamptz disimpan UTC (benar), tapi view lama memformatnya
        // pakai date('H:i', strtotime(...)) — yang SELALU memakai
        // timezone default PHP (UTC), mengabaikan zona kantor pegawai.
        // Pola konversi yang benar (setTimezone sebelum format) sudah ada
        // di EloquentAttendanceRepository — diterapkan di sini juga,
        // lihat komentar setara di AttendanceRecapController::dailyRows().
        $officeTimezone = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $user->employee_id)
            ->value('o.timezone') ?? 'Asia/Makassar';

        $tz = new \DateTimeZone($officeTimezone);

        $riwayat = DB::table('att_attendance_records')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('work_date')
            ->limit(14)
            ->get()
            ->map(function ($r) use ($tz) {
                $format = fn (?string $iso) => $iso ? (new DateTimeImmutable($iso))->setTimezone($tz)->format('H:i') : null;

                $r->check_in_local = $format($r->check_in_at);
                $r->break_start_local = $format($r->break_start_at);
                $r->break_end_local = $format($r->break_end_at);
                $r->check_out_local = $format($r->check_out_at);

                return $r;
            });

        $todayOffice = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        $todayRecord = $riwayat->firstWhere('work_date', $todayOffice);

        return view('attendance.create', ['riwayat' => $riwayat, 'todayRecord' => $todayRecord]);
    }

    public function store(RecordGpsAttendanceForm $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $point = new GeoCoordinate((float) $request->input('latitude'), (float) $request->input('longitude'));

            $outcome = $this->record->handle(
                employeeId: $user->employee_id,
                point: $point,
                action: AttendanceAction::from($request->string('action')->toString()),
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (OutsideGeofence|AlreadyCompletedToday|AttendanceActionNotAllowed|BreakNotYetAllowed|InvalidArgumentException|RuntimeException $e) {
            return redirect()->route('attendance.create')->with('gagal', $e->getMessage());
        }

        $pesan = match ($outcome->action) {
            AttendanceAction::CheckIn => $outcome->status === AttendanceStatus::Telat
                ? 'Absen masuk tercatat — Anda tercatat TERLAMBAT.'
                : 'Absen masuk tercatat. Selamat bekerja!',
            AttendanceAction::BreakStart => 'Mulai istirahat tercatat. Selamat istirahat!',
            AttendanceAction::BreakEnd => 'Kembali dari istirahat tercatat. Selamat bekerja kembali!',
            AttendanceAction::CheckOut => 'Absen pulang tercatat. Sampai jumpa besok!',
        };

        return redirect()->route('attendance.create')->with('sukses', $pesan);
    }

    public function createOutside(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $riwayat = DB::table('att_outside_attendance_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('work_date')
            ->limit(14)
            ->get();

        return view('attendance.create-outside', ['riwayat' => $riwayat]);
    }

    public function storeOutside(RequestOutsideAttendanceForm $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $this->requestOutside->handle(
                employeeId: $user->employee_id,
                workDate: new DateTimeImmutable($request->string('work_date')->toString()),
                reason: $request->string('reason')->toString(),
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('attendance.outside.create')->with('gagal', $e->getMessage());
        }

        return redirect()->route('attendance.outside.create')->with('sukses', 'Pengajuan absen luar kantor terkirim, menunggu persetujuan Pimpinan Kantor.');
    }
}
