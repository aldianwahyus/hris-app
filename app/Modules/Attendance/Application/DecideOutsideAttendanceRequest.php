<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Attendance\Domain\AttendanceSource;
use App\Modules\Attendance\Domain\AttendanceStatus;
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
 * Keputusan Pimpinan Kantor atas pengajuan absen luar kantor. Berbeda
 * dari ShiftSwap yang cukup ubah status, di sini PERSETUJUAN juga
 * meng-upsert att_attendance_records — meniru persis pola
 * RecordGpsAttendance::handle() (insertOrIgnore → lockForUpdate →
 * update, timestamptz dikonversi eksplisit ke UTC) TAPI TANPA
 * GeofencePolicy sama sekali: ini override manual yang sudah disetujui
 * manusia, bukan pindaian GPS langsung.
 *
 * check_in_at/check_out_at diisi ke jam kerja resmi kantor
 * (ATT_WORK_START_TIME/ATT_WORK_END_TIME) pada tanggal yang diajukan —
 * MENIMPA absensi GPS yang mungkin sudah ada hari itu (keputusan
 * pimpinan yang disetujui menang), kondisi lama dicatat di audit
 * sebagai oldValues.
 */
final class DecideOutsideAttendanceRequest
{
    public function __construct(
        private readonly AuditRepository $audit,
        private readonly ParameterResolver $parameters,
    ) {}

    public function approve(string $requestId, string $approverId, AuditActor $actor): void
    {
        DB::transaction(function () use ($requestId, $approverId, $actor) {
            $request = DB::table('att_outside_attendance_requests')
                ->where('id', $requestId)
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new RuntimeException('Pengajuan absen luar kantor tidak ditemukan.');
            }

            if ($request->status !== 'pending') {
                return;
            }

            $now = new DateTimeImmutable;

            DB::table('att_outside_attendance_requests')->where('id', $requestId)->update([
                'status' => 'approved',
                'approver_id' => $approverId,
                'decided_at' => $now,
                'updated_at' => $now,
            ]);

            $office = DB::table('emp_employees as e')
                ->join('md_offices as o', 'o.id', '=', 'e.office_id')
                ->where('e.id', $request->employee_id)
                ->select('o.timezone')
                ->first();

            if ($office === null) {
                throw new RuntimeException('Kantor pegawai tidak ditemukan — tidak dapat mencatat absen.');
            }

            $timezone = new DateTimeZone($office->timezone);
            $workDateAtOffice = new DateTimeImmutable($request->work_date, $timezone);

            $workStart = $this->parameters->string('ATT_WORK_START_TIME', AsOfDate::on($workDateAtOffice));
            $workEnd = $this->parameters->string('ATT_WORK_END_TIME', AsOfDate::on($workDateAtOffice));

            $checkInAtOffice = $this->atTime($workDateAtOffice, $workStart);
            $checkOutAtOffice = $this->atTime($workDateAtOffice, $workEnd);

            // Kolom timestamptz DITULIS dalam UTC secara eksplisit — pola sama
            // RecordGpsAttendance, lihat catatan di sana soal alasannya.
            $checkInUtc = $checkInAtOffice->setTimezone(new DateTimeZone('UTC'));
            $checkOutUtc = $checkOutAtOffice->setTimezone(new DateTimeZone('UTC'));
            $nowUtc = $now->setTimezone(new DateTimeZone('UTC'));

            DB::table('att_attendance_records')->insertOrIgnore([
                'id' => (string) Uuid7::generate(),
                'employee_id' => $request->employee_id,
                'work_date' => $request->work_date,
                'created_at' => $nowUtc,
                'updated_at' => $nowUtc,
                'version' => 1,
            ]);

            $record = DB::table('att_attendance_records')
                ->where('employee_id', $request->employee_id)
                ->where('work_date', $request->work_date)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw new RuntimeException('Baris absensi tidak ditemukan setelah disisipkan.');
            }

            $oldValues = [
                'check_in_at' => $record->check_in_at,
                'check_in_source' => $record->check_in_source,
                'check_out_at' => $record->check_out_at,
                'check_out_source' => $record->check_out_source,
                'status' => $record->status,
            ];

            DB::table('att_attendance_records')->where('id', $record->id)->update([
                'check_in_at' => $checkInUtc,
                'check_in_source' => AttendanceSource::LuarKantor->value,
                'check_in_lat' => null,
                'check_in_lng' => null,
                'check_out_at' => $checkOutUtc,
                'check_out_source' => AttendanceSource::LuarKantor->value,
                'check_out_lat' => null,
                'check_out_lng' => null,
                'status' => AttendanceStatus::Hadir->value,
                'updated_at' => $nowUtc,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'outside_attendance_request',
                auditableId: $requestId,
                action: AuditAction::Approved,
                oldValues: $oldValues,
                newValues: [
                    'check_in_source' => AttendanceSource::LuarKantor->value,
                    'check_out_source' => AttendanceSource::LuarKantor->value,
                    'status' => AttendanceStatus::Hadir->value,
                ],
                contextRef: $request->request_number,
            ));
        });
    }

    public function reject(string $requestId, string $approverId, AuditActor $actor): void
    {
        DB::transaction(function () use ($requestId, $approverId, $actor) {
            $request = DB::table('att_outside_attendance_requests')
                ->where('id', $requestId)
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new RuntimeException('Pengajuan absen luar kantor tidak ditemukan.');
            }

            if ($request->status !== 'pending') {
                return;
            }

            $now = new DateTimeImmutable;

            DB::table('att_outside_attendance_requests')->where('id', $requestId)->update([
                'status' => 'rejected',
                'approver_id' => $approverId,
                'decided_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'outside_attendance_request',
                auditableId: $requestId,
                action: AuditAction::Rejected,
                contextRef: $request->request_number,
            ));
        });
    }

    private function atTime(DateTimeImmutable $date, string $hi): DateTimeImmutable
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hi, $m)) {
            throw new RuntimeException("Format jam kerja tidak valid: \"{$hi}\" (harus H:i).");
        }

        return $date->setTime((int) $m[1], (int) $m[2]);
    }
}
