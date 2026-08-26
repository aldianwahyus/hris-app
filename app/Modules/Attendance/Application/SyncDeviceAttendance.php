<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Attendance\Domain\AttendanceDayPolicy;
use App\Modules\Attendance\Domain\AttendanceDeviceGateway;
use App\Modules\Attendance\Domain\AttendanceSource;
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

/**
 * Menyerap peristiwa pindai mesin fingerprint ke att_attendance_records
 * — dipanggil berkala oleh Console/Commands/SyncAttendanceDevice.
 *
 * Berbeda dengan RecordGpsAttendance (tindakan eksplisit sekali tekan),
 * pindai mesin bersifat pasif dan bisa berkali-kali sehari (mis. keluar-
 * masuk istirahat) — pindai PERTAMA tetap "masuk", tapi "pulang" TERUS
 * diperbarui ke pindai TERAKHIR yang terlihat, bukan dibekukan setelah
 * pindai kedua. Ini konvensi umum mesin absensi (jam kerja = pindai
 * terakhir - pindai pertama), bukan aturan dua-langkah seperti ESS.
 */
final class SyncDeviceAttendance
{
    public function __construct(
        private readonly AttendanceDeviceGateway $gateway,
        private readonly AuditRepository $audit,
        private readonly ParameterResolver $parameters,
    ) {}

    public function handle(DateTimeImmutable $since): SyncResult
    {
        $events = $this->gateway->fetchScansSince($since);

        $processedIds = [];
        $matched = 0;
        $unmatched = 0;

        foreach ($events as $event) {
            $employee = DB::table('emp_employees as e')
                ->join('md_offices as o', 'o.id', '=', 'e.office_id')
                ->where('e.fingerprint_device_pin', $event->devicePin)
                ->select('e.id as employee_id', 'o.timezone')
                ->first();

            $processedIds[] = $event->logId;

            if ($employee === null) {
                $unmatched++;

                continue;
            }

            DB::table('att_device_scan_logs')->where('id', $event->logId)->update(['employee_id' => $employee->employee_id]);

            $this->applyScan($employee->employee_id, $employee->timezone, $event->scannedAt);
            $matched++;
        }

        $this->gateway->acknowledge($processedIds);

        return new SyncResult(matched: $matched, unmatched: $unmatched);
    }

    private function applyScan(string $employeeId, string $officeTimezone, DateTimeImmutable $scannedAt): void
    {
        $scannedAtOffice = $scannedAt->setTimezone(new DateTimeZone($officeTimezone));
        $workDate = $scannedAtOffice->format('Y-m-d');

        // Lihat catatan setara di RecordGpsAttendance — kolom timestamptz
        // WAJIB ditulis dalam UTC eksplisit agar tidak tergeser sebesar
        // selisih zona kantor terhadap UTC (Laravel memformat objek
        // DateTimeImmutable tanpa offset sebelum dikirim ke Postgres).
        $scannedAtUtc = $scannedAtOffice->setTimezone(new DateTimeZone('UTC'));

        DB::transaction(function () use ($employeeId, $workDate, $scannedAtOffice, $scannedAtUtc) {
            DB::table('att_attendance_records')->insertOrIgnore([
                'id' => (string) Uuid7::generate(),
                'employee_id' => $employeeId,
                'work_date' => $workDate,
                'created_at' => $scannedAtUtc,
                'updated_at' => $scannedAtUtc,
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

            $actor = AuditActor::system('attendance-device-sync');

            if ($record->check_in_at === null) {
                $workStart = $this->parameters->string('ATT_WORK_START_TIME', AsOfDate::on($scannedAtOffice));
                $grace = $this->parameters->integer('ATT_LATE_GRACE_MINUTES', AsOfDate::on($scannedAtOffice));
                $status = AttendanceDayPolicy::determineCheckInStatus($scannedAtOffice, $workStart, $grace);

                DB::table('att_attendance_records')->where('id', $record->id)->update([
                    'check_in_at' => $scannedAtUtc,
                    'check_in_source' => AttendanceSource::Fingerprint->value,
                    'status' => $status->value,
                    'updated_at' => $scannedAtUtc,
                ]);

                $this->logAudit($actor, $record->id, 'masuk', $scannedAtOffice);

                return;
            }

            $existingCheckOut = $record->check_out_at === null ? null : new DateTimeImmutable($record->check_out_at);

            if ($existingCheckOut !== null && $scannedAtOffice <= $existingCheckOut) {
                return; // pindai lebih lama dari catatan pulang yang sudah ada — abaikan
            }

            DB::table('att_attendance_records')->where('id', $record->id)->update([
                'check_out_at' => $scannedAtUtc,
                'check_out_source' => AttendanceSource::Fingerprint->value,
                'updated_at' => $scannedAtUtc,
            ]);

            $this->logAudit($actor, $record->id, 'pulang', $scannedAtOffice);
        });
    }

    private function logAudit(AuditActor $actor, string $recordId, string $jenis, DateTimeImmutable $at): void
    {
        $this->audit->append(new AuditEntry(
            occurredAt: $at,
            actor: $actor,
            auditableType: 'attendance_record',
            auditableId: $recordId,
            action: AuditAction::AttendanceRecorded,
            newValues: ['jenis' => $jenis, 'sumber' => AttendanceSource::Fingerprint->value],
        ));
    }
}
