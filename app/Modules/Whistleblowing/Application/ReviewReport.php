<?php

declare(strict_types=1);

namespace App\Modules\Whistleblowing\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * hr_approver menindaklanjuti laporan Whistleblowing (Fase 2).
 * `diproses` HANYA dari 'baru'; `selesai` HANYA dari 'diproses' — pola
 * SAMA ReviewDeletionRequest (UU PDP). Peninjau BUKAN pelapor —
 * tindakan ini SELALU tercatat dengan identitas peninjau APA ADANYA
 * (privasi hanya berlaku ke pelapor, bukan ke penindak lanjut).
 */
final class ReviewReport
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function startProcessing(string $reportId, AuditActor $actor): void
    {
        $this->transition($reportId, 'baru', 'diproses', AuditAction::Approved, $actor, null);
    }

    public function complete(string $reportId, AuditActor $actor, string $resolutionNotes): void
    {
        $this->transition($reportId, 'diproses', 'selesai', AuditAction::Executed, $actor, $resolutionNotes);
    }

    private function transition(string $reportId, string $fromStatus, string $toStatus, AuditAction $action, AuditActor $actor, ?string $resolutionNotes): void
    {
        DB::transaction(function () use ($reportId, $fromStatus, $toStatus, $action, $actor, $resolutionNotes) {
            $report = DB::table('wb_reports')->where('id', $reportId)->lockForUpdate()->first();

            if ($report === null) {
                throw new DomainException('Laporan tidak ditemukan.');
            }

            if ($report->status !== $fromStatus) {
                throw new DomainException("Laporan ini berstatus \"{$report->status}\", bukan \"{$fromStatus}\".");
            }

            $now = new DateTimeImmutable;

            $update = [
                'status' => $toStatus,
                'reviewed_by' => $actor->actorId,
                'reviewed_at' => $now,
            ];

            if ($resolutionNotes !== null) {
                $update['resolution_notes'] = $resolutionNotes;
            }

            DB::table('wb_reports')->where('id', $reportId)->update($update);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'wb_report',
                auditableId: $reportId,
                action: $action,
                contextRef: $reportId,
            ));
        });
    }
}
