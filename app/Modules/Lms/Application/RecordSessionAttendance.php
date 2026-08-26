<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Absensi per sesi (hari pertemuan) — HC mencatat status HADIR/IZIN/
 * SAKIT/ALPA per pendaftar yang SUDAH DISETUJUI untuk satu sesi. Upsert
 * (insert kalau belum ada baris, update kalau sudah — HC boleh
 * mengoreksi absensi hari itu), satu entri audit untuk SELURUH submit
 * (bukan per baris — mengikuti submit form sekali per sesi, pola sama
 * RoleFeatureMapController::update() yang mencatat per-perubahan
 * bertingkat, di sini per-sesi karena granularitasnya wajar begitu).
 */
final class RecordSessionAttendance
{
    private const VALID_STATUSES = ['hadir', 'izin', 'sakit', 'alpa'];

    public function __construct(private readonly AuditRepository $audit) {}

    /** @param  array<string, string>  $statusesByEnrollmentId */
    public function handle(string $sessionId, array $statusesByEnrollmentId, AuditActor $actor, string $recordedBy): void
    {
        foreach ($statusesByEnrollmentId as $status) {
            if (! in_array($status, self::VALID_STATUSES, true)) {
                throw new InvalidArgumentException("Status kehadiran tidak dikenali: \"{$status}\".");
            }
        }

        DB::transaction(function () use ($sessionId, $statusesByEnrollmentId, $actor, $recordedBy) {
            $session = DB::table('lms_course_sessions as s')
                ->join('lms_course_batches as b', 'b.id', '=', 's.batch_id')
                ->where('s.id', $sessionId)
                ->select('s.id', 'b.status as batch_status')
                ->first();

            if ($session === null) {
                throw new RuntimeException('Sesi pelatihan tidak ditemukan.');
            }

            if ($session->batch_status === 'cancelled') {
                throw new InvalidArgumentException('Jadwal pelatihan ini sudah dibatalkan — absensi tidak dapat dicatat.');
            }

            $now = new DateTimeImmutable;

            foreach ($statusesByEnrollmentId as $enrollmentId => $status) {
                $existing = DB::table('lms_attendances')
                    ->where('session_id', $sessionId)
                    ->where('enrollment_id', $enrollmentId)
                    ->first();

                if ($existing === null) {
                    DB::table('lms_attendances')->insert([
                        'id' => (string) Uuid7::generate(),
                        'session_id' => $sessionId,
                        'enrollment_id' => $enrollmentId,
                        'status' => $status,
                        'recorded_by' => $recordedBy,
                        'recorded_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'version' => 1,
                    ]);
                } else {
                    DB::table('lms_attendances')->where('id', $existing->id)->update([
                        'status' => $status,
                        'recorded_by' => $recordedBy,
                        'recorded_at' => $now,
                        'updated_at' => $now,
                        'version' => $existing->version + 1,
                    ]);
                }
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'lms_session_attendance',
                auditableId: $sessionId,
                action: AuditAction::Updated,
                newValues: $statusesByEnrollmentId,
            ));
        });
    }
}
