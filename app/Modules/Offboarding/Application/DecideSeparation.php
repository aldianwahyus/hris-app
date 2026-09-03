<?php

declare(strict_types=1);

namespace App\Modules\Offboarding\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * hr_approver (checker) memutuskan pengajuan pemisahan — pola PERSIS
 * DecideNewEmployeeRequest. Disetujui → checklist clearance langsung
 * dibangkitkan DALAM transaksi yang sama (GenerateClearanceChecklist
 * ada di modul yang SAMA, jadi boleh diimpor langsung — beda dengan
 * Onboarding yang harus dipicu dari lapisan Interfaces karena
 * lintas modul, lihat catatan di GenerateOnboardingChecklist).
 */
final class DecideSeparation
{
    public function __construct(
        private readonly AuditRepository $audit,
        private readonly GenerateClearanceChecklist $generateClearance,
    ) {}

    public function approve(string $separationId, AuditActor $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($separationId, $actor, $note) {
            $request = $this->lockPending($separationId, $actor);

            $now = new DateTimeImmutable;

            DB::table('off_separation_requests')->where('id', $separationId)->update([
                'status' => 'approved',
                'approver_id' => $actor->actorId,
                'decided_at' => $now,
                'decision_note' => $note,
                'updated_at' => $now,
                'version' => $request->version + 1,
            ]);

            $this->generateClearance->handle($separationId, $request->employee_id);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'off_separation_request',
                auditableId: $separationId,
                action: AuditAction::Approved,
                contextRef: $separationId,
            ));
        });
    }

    public function reject(string $separationId, AuditActor $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($separationId, $actor, $note) {
            $request = $this->lockPending($separationId, $actor);
            $now = new DateTimeImmutable;

            DB::table('off_separation_requests')->where('id', $separationId)->update([
                'status' => 'rejected',
                'approver_id' => $actor->actorId,
                'decided_at' => $now,
                'decision_note' => $note,
                'updated_at' => $now,
                'version' => $request->version + 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'off_separation_request',
                auditableId: $separationId,
                action: AuditAction::Rejected,
                contextRef: $separationId,
            ));
        });
    }

    /** @return object{id: string, employee_id: string, requested_by: string, version: int} */
    private function lockPending(string $separationId, AuditActor $actor): object
    {
        $request = DB::table('off_separation_requests')
            ->where('id', $separationId)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->first();

        if ($request === null) {
            throw new DomainException('Pengajuan pemisahan tidak ditemukan atau sudah diputus sebelumnya.');
        }

        if ($request->requested_by === $actor->actorId) {
            throw new DomainException('Tidak dapat memutuskan pengajuan pemisahan yang Anda ajukan sendiri.');
        }

        /** @var object{id: string, employee_id: string, requested_by: string, version: int} $request */
        return $request;
    }
}
