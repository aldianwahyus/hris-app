<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Models\User;
use App\Notifications\RequestDecided;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Keputusan Atasan Langsung atas pendaftaran pelatihan — pola PERSIS
 * DecideOutsideAttendanceRequest (lock-for-update, guard status pending,
 * audit Approved/Rejected dengan contextRef nomor pendaftaran).
 */
final class DecideEnrollment
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function approve(string $enrollmentId, string $approverId, AuditActor $actor): void
    {
        $this->decide($enrollmentId, $approverId, 'approved', AuditAction::Approved, $actor, null);
    }

    public function reject(string $enrollmentId, string $approverId, AuditActor $actor, ?string $note): void
    {
        $this->decide($enrollmentId, $approverId, 'rejected', AuditAction::Rejected, $actor, $note);
    }

    private function decide(string $enrollmentId, string $approverId, string $status, AuditAction $action, AuditActor $actor, ?string $note): void
    {
        $decided = DB::transaction(function () use ($enrollmentId, $approverId, $status, $action, $actor, $note) {
            $enrollment = DB::table('lms_enrollments')->where('id', $enrollmentId)->lockForUpdate()->first();

            if ($enrollment === null) {
                throw new RuntimeException('Pendaftaran pelatihan tidak ditemukan.');
            }

            if ($enrollment->status !== 'pending') {
                return null;
            }

            $now = new DateTimeImmutable;

            DB::table('lms_enrollments')->where('id', $enrollmentId)->update([
                'status' => $status,
                'approver_id' => $approverId,
                'decided_at' => $now,
                'decision_note' => $note,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'lms_enrollment',
                auditableId: $enrollmentId,
                action: $action,
                contextRef: $enrollment->enrollment_number,
            ));

            return $enrollment;
        });

        // Notifikasi DI LUAR transaksi (mengirim mail/DB write ke tabel
        // notifications tidak boleh membatalkan keputusan approval bila
        // gagal) — pola sengaja beda dari sisa method ini yang memang
        // butuh transaksi untuk lock+update+audit atomik. Pendaftaran
        // pelatihan SATU tahap — setiap keputusan sudah final.
        if ($decided !== null) {
            $user = User::query()->where('employee_id', $decided->employee_id)->first();
            $user?->notify(new RequestDecided($decided->enrollment_number, 'pendaftaran pelatihan', $status === 'approved', $note));
        }
    }
}
