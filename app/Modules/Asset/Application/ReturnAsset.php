<?php

declare(strict_types=1);

namespace App\Modules\Asset\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mengembalikan aset yang sedang ditugaskan — kondisi kembali
 * menentukan status aset berikutnya: "baik" → langsung 'tersedia' lagi
 * (siap ditugaskan ke pegawai lain), rusak (ringan/berat) → 'perbaikan'
 * (TIDAK otomatis tersedia lagi, wajib ditinjau manual dulu).
 */
final class ReturnAsset
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $assignmentId, string $returnedCondition, ?string $notes, AuditActor $actor): void
    {
        DB::transaction(function () use ($assignmentId, $returnedCondition, $notes, $actor) {
            $assignment = DB::table('ast_assignments')->where('id', $assignmentId)->lockForUpdate()->first();

            if ($assignment === null) {
                throw new DomainException('Penugasan aset tidak ditemukan.');
            }

            if ($assignment->returned_at !== null) {
                throw new DomainException('Aset ini sudah pernah dikembalikan.');
            }

            $now = new DateTimeImmutable;

            DB::table('ast_assignments')->where('id', $assignmentId)->update([
                'returned_at' => $now,
                'returned_condition' => $returnedCondition,
                'notes' => $notes,
                'updated_at' => $now,
                'version' => $assignment->version + 1,
            ]);

            $newAssetStatus = $returnedCondition === 'baik' ? 'tersedia' : 'perbaikan';

            DB::table('ast_assets')->where('id', $assignment->asset_id)->update([
                'status' => $newAssetStatus,
                'condition' => $returnedCondition,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'ast_assignment',
                auditableId: $assignmentId,
                action: AuditAction::Updated,
                newValues: ['returned_at' => $now->format('Y-m-d H:i:s'), 'returned_condition' => $returnedCondition],
            ));
        });
    }
}
