<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Attendance\Domain\AlreadyCompletedToday;
use App\Modules\Attendance\Domain\AttendanceAction;
use App\Modules\Attendance\Domain\AttendanceActionNotAllowed;
use App\Modules\Attendance\Domain\AttendanceBreakPolicy;
use App\Modules\Attendance\Domain\AttendanceDayPolicy;
use App\Modules\Attendance\Domain\AttendanceSource;
use App\Modules\Attendance\Domain\AttendanceStatus;
use App\Modules\Attendance\Domain\GeoCoordinate;
use App\Modules\Attendance\Domain\GeofencePolicy;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * Absen GPS dari layar ESS — SELALU atas nama pegawai yang sedang
 * masuk (ownership, tidak ada parameter employee_id).
 *
 * "Hari kerja" (work_date) dihitung pada ZONA WAKTU KANTOR pegawai,
 * bukan zona waktu server: Bank beroperasi lintas WITA/WIB (KC
 * Surabaya), dan tanggal absensi yang salah zona berarti hari kerja
 * yang salah.
 *
 * EMPAT jenis aksi (AttendanceAction), klien MENYATAKAN maksudnya
 * secara eksplisit (bukan disimpulkan dari urutan scan seperti model
 * 2-tahap lama): Masuk → (opsional: Istirahat → Kembali) → Pulang.
 * Istirahat/Kembali OPSIONAL — pegawai boleh langsung Masuk→Pulang.
 * TAPI begitu Istirahat tercatat, Pulang diblokir sampai Kembali juga
 * tercatat (mencegah pegawai "pulang" padahal masih tercatat sedang
 * istirahat di laporan). Istirahat/Kembali masing-masing punya jendela
 * waktu paling awal (ATT_BREAK_START_TIME/ATT_BREAK_RETURN_TIME, lihat
 * AttendanceBreakPolicy) — tidak ada batas atas.
 *
 * Sumbernya GPS di sini, atau fingerprint lewat SyncDeviceAttendance —
 * TAPI fingerprint SENGAJA TIDAK ikut memakai model 4-aksi ini (lihat
 * docblock SyncDeviceAttendance: mesin absensi memakai konvensi
 * "pindai pertama = masuk, pindai terakhir = pulang" yang sudah
 * menampung istirahat secara implisit tanpa kolom terpisah) — kedua
 * jalur tetap bermuara ke baris att_attendance_records yang sama,
 * hanya kolom break_start_at/break_end_at yang murni jalur GPS/ESS.
 */
final class RecordGpsAttendance
{
    public function __construct(
        private readonly AuditRepository $audit,
        private readonly ParameterResolver $parameters,
    ) {}

    public function handle(string $employeeId, GeoCoordinate $point, AttendanceAction $action, AuditActor $actor): AttendanceOutcome
    {
        $office = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $employeeId)
            ->select('o.latitude', 'o.longitude', 'o.geofence_radius_m', 'o.timezone')
            ->first();

        if ($office === null || $office->latitude === null || $office->longitude === null) {
            throw new RuntimeException('Koordinat kantor belum tersedia — tidak dapat memvalidasi lokasi absen.');
        }

        $officeCenter = new GeoCoordinate((float) $office->latitude, (float) $office->longitude);
        GeofencePolicy::guard($officeCenter, $point, (int) $office->geofence_radius_m);

        $nowAtOffice = new DateTimeImmutable('now', new DateTimeZone($office->timezone));
        $workDate = $nowAtOffice->format('Y-m-d');

        // Kolom timestamptz DITULIS dalam UTC secara eksplisit — lihat
        // catatan lengkap di SyncDeviceAttendance (Postgres menafsirkan
        // ulang jam dinding memakai TimeZone sesi, bukan zona kantor).
        $nowUtc = $nowAtOffice->setTimezone(new DateTimeZone('UTC'));

        return DB::transaction(function () use ($employeeId, $point, $nowAtOffice, $nowUtc, $workDate, $action, $actor) {
            DB::table('att_attendance_records')->insertOrIgnore([
                'id' => (string) Uuid7::generate(),
                'employee_id' => $employeeId,
                'work_date' => $workDate,
                'created_at' => $nowUtc,
                'updated_at' => $nowUtc,
                'version' => 1,
            ]);

            $record = DB::table('att_attendance_records')
                ->where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw new RuntimeException('Baris absensi tidak ditemukan setelah disisipkan.');
            }

            return match ($action) {
                AttendanceAction::CheckIn => $this->recordCheckIn($record, $point, $nowAtOffice, $nowUtc, $actor),
                AttendanceAction::BreakStart => $this->recordBreakStart($record, $point, $nowAtOffice, $nowUtc, $actor),
                AttendanceAction::BreakEnd => $this->recordBreakEnd($record, $point, $nowAtOffice, $nowUtc, $actor),
                AttendanceAction::CheckOut => $this->recordCheckOut($record, $point, $nowAtOffice, $nowUtc, $actor),
            };
        });
    }

    private function recordCheckIn(stdClass $record, GeoCoordinate $point, DateTimeImmutable $nowAtOffice, DateTimeImmutable $nowUtc, AuditActor $actor): AttendanceOutcome
    {
        if ($record->check_in_at !== null) {
            throw AttendanceActionNotAllowed::alreadyCheckedIn();
        }

        $workStart = $this->parameters->string('ATT_WORK_START_TIME', AsOfDate::on($nowAtOffice));
        $grace = $this->parameters->integer('ATT_LATE_GRACE_MINUTES', AsOfDate::on($nowAtOffice));
        $status = AttendanceDayPolicy::determineCheckInStatus($nowAtOffice, $workStart, $grace);

        DB::table('att_attendance_records')->where('id', $record->id)->update([
            'check_in_at' => $nowUtc,
            'check_in_source' => AttendanceSource::Gps->value,
            'check_in_lat' => $point->latitude,
            'check_in_lng' => $point->longitude,
            'status' => $status->value,
            'updated_at' => $nowUtc,
        ]);

        $this->logAudit($actor, $record->id, 'masuk', $nowAtOffice);

        return new AttendanceOutcome(AttendanceAction::CheckIn, $status);
    }

    private function recordBreakStart(stdClass $record, GeoCoordinate $point, DateTimeImmutable $nowAtOffice, DateTimeImmutable $nowUtc, AuditActor $actor): AttendanceOutcome
    {
        if ($record->check_in_at === null) {
            throw AttendanceActionNotAllowed::mustCheckInFirst();
        }

        if ($record->check_out_at !== null) {
            throw AlreadyCompletedToday::create();
        }

        if ($record->break_start_at !== null) {
            throw AttendanceActionNotAllowed::breakAlreadyStarted();
        }

        $allowedFrom = $this->parameters->string('ATT_BREAK_START_TIME', AsOfDate::on($nowAtOffice));
        AttendanceBreakPolicy::guardBreakStart($nowAtOffice, $allowedFrom);

        DB::table('att_attendance_records')->where('id', $record->id)->update([
            'break_start_at' => $nowUtc,
            'break_start_source' => AttendanceSource::Gps->value,
            'break_start_lat' => $point->latitude,
            'break_start_lng' => $point->longitude,
            'updated_at' => $nowUtc,
        ]);

        $this->logAudit($actor, $record->id, 'istirahat', $nowAtOffice);

        return new AttendanceOutcome(AttendanceAction::BreakStart, null);
    }

    private function recordBreakEnd(stdClass $record, GeoCoordinate $point, DateTimeImmutable $nowAtOffice, DateTimeImmutable $nowUtc, AuditActor $actor): AttendanceOutcome
    {
        if ($record->break_start_at === null) {
            throw AttendanceActionNotAllowed::breakNotStarted();
        }

        if ($record->break_end_at !== null) {
            throw AttendanceActionNotAllowed::breakAlreadyEnded();
        }

        $allowedFrom = $this->parameters->string('ATT_BREAK_RETURN_TIME', AsOfDate::on($nowAtOffice));
        AttendanceBreakPolicy::guardBreakEnd($nowAtOffice, $allowedFrom);

        DB::table('att_attendance_records')->where('id', $record->id)->update([
            'break_end_at' => $nowUtc,
            'break_end_source' => AttendanceSource::Gps->value,
            'break_end_lat' => $point->latitude,
            'break_end_lng' => $point->longitude,
            'updated_at' => $nowUtc,
        ]);

        $this->logAudit($actor, $record->id, 'kembali', $nowAtOffice);

        return new AttendanceOutcome(AttendanceAction::BreakEnd, null);
    }

    private function recordCheckOut(stdClass $record, GeoCoordinate $point, DateTimeImmutable $nowAtOffice, DateTimeImmutable $nowUtc, AuditActor $actor): AttendanceOutcome
    {
        if ($record->check_in_at === null) {
            throw AttendanceActionNotAllowed::mustCheckInFirst();
        }

        if ($record->check_out_at !== null) {
            throw AlreadyCompletedToday::create();
        }

        if ($record->break_start_at !== null && $record->break_end_at === null) {
            throw AttendanceActionNotAllowed::mustEndBreakFirst();
        }

        DB::table('att_attendance_records')->where('id', $record->id)->update([
            'check_out_at' => $nowUtc,
            'check_out_source' => AttendanceSource::Gps->value,
            'check_out_lat' => $point->latitude,
            'check_out_lng' => $point->longitude,
            'updated_at' => $nowUtc,
        ]);

        $this->logAudit($actor, $record->id, 'pulang', $nowAtOffice);

        return new AttendanceOutcome(AttendanceAction::CheckOut, AttendanceStatus::from($record->status));
    }

    private function logAudit(AuditActor $actor, string $recordId, string $jenis, DateTimeImmutable $at): void
    {
        $this->audit->append(new AuditEntry(
            occurredAt: $at,
            actor: $actor,
            auditableType: 'attendance_record',
            auditableId: $recordId,
            action: AuditAction::AttendanceRecorded,
            newValues: ['jenis' => $jenis, 'sumber' => AttendanceSource::Gps->value],
        ));
    }
}
