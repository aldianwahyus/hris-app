<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mengajukan absen luar kantor dari layar ESS — pegawai lapangan
 * mengajukan tanggal+alasan, disetujui Pimpinan Kantor SATU TAHAP (lihat
 * OutsideAttendanceApprovalController), bukan lewat GeofencePolicy sama
 * sekali. Workflow Engine di sini HANYA untuk pelacakan SLA/pengingat,
 * bukan penentu approver (pola sama SubmitShiftSwapRequest).
 *
 * Tanggal LAMPAU diizinkan (beda dari Tukar Shift yang cuma masa depan)
 * — kasus utama fitur ini adalah menyusulkan absen setelah kerja
 * lapangan selesai, bukan merencanakan jauh ke depan.
 */
final class RequestOutsideAttendance
{
    private const MAX_DAYS_IN_PAST = 7;

    public function __construct(
        private readonly WorkflowInstanceRepository $workflow,
        private readonly AuditRepository $audit,
    ) {}

    public function handle(
        string $employeeId,
        DateTimeImmutable $workDate,
        string $reason,
        AuditActor $actor,
    ): string {
        // Bug ditemukan lewat audit kode: "today" sebelumnya dihitung di
        // zona waktu default PHP (UTC, config/app.php), bukan zona kantor
        // pegawai — dekat batas hari, jendela 7-hari-ke-belakang bisa
        // menerima/menolak tanggal yang salah dibanding "hari ini"
        // sesungguhnya di kantor pegawai (sama seperti RecordGpsAttendance
        // menghitung work_date pada zona kantor, bukan server).
        $officeTimezone = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $employeeId)
            ->value('o.timezone') ?? 'Asia/Makassar';

        // Dibandingkan sebagai STRING tanggal kalender ('Y-m-d'), bukan
        // instant DateTimeImmutable — $workDate sendiri tidak membawa info
        // zona (dibentuk dari input tanggal polos "YYYY-MM-DD" di
        // AttendanceController::storeOutside, jadi selalu tengah malam UTC
        // menurut PHP). Membandingkan instant lintas dua rangka acuan zona
        // berbeda (workDate=UTC vs earliestAllowed=zona kantor) masih bisa
        // meleset di batas hari — membandingkan tanggal kalender murni
        // menghindarinya sama sekali, dan urutan leksikografis format ISO
        // (Y-m-d) sudah benar tanpa perlu parsing lebih lanjut.
        $today = new DateTimeImmutable('today', new DateTimeZone($officeTimezone));
        $earliestAllowedString = $today->modify('-'.self::MAX_DAYS_IN_PAST.' days')->format('Y-m-d');
        $workDateString = $workDate->format('Y-m-d');

        if ($workDateString < $earliestAllowedString) {
            throw new InvalidArgumentException(
                'Tanggal absen luar kantor tidak boleh lebih dari '.self::MAX_DAYS_IN_PAST.' hari ke belakang.'
            );
        }

        $existing = DB::table('att_outside_attendance_requests')
            ->where('employee_id', $employeeId)
            ->where('work_date', $workDateString)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException('Sudah ada pengajuan absen luar kantor untuk tanggal ini.');
        }

        return DB::transaction(function () use ($employeeId, $workDateString, $reason, $actor) {
            $now = new DateTimeImmutable;
            $requestId = (string) Uuid7::generate();

            DB::table('att_outside_attendance_requests')->insert([
                'id' => $requestId,
                'request_number' => $this->nextRequestNumber($now),
                'employee_id' => $employeeId,
                'work_date' => $workDateString,
                'reason' => $reason,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $instance = $this->workflow->startFor('outside_attendance_request', $requestId, $employeeId, $now);

            DB::table('att_outside_attendance_requests')->where('id', $requestId)->update(['wf_instance_id' => $instance->id]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'outside_attendance_request',
                auditableId: $requestId,
                action: AuditAction::Submitted,
                newValues: ['work_date' => $workDateString, 'reason' => $reason],
            ));

            return DB::table('att_outside_attendance_requests')->where('id', $requestId)->value('request_number');
        });
    }

    private function nextRequestNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('ALK/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('att_outside_attendance_requests')
            ->where('request_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
