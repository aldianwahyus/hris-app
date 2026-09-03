<?php

declare(strict_types=1);

namespace App\Modules\Asset\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Menugaskan satu aset ke satu pegawai — HANYA aset berstatus
 * 'tersedia' yang bisa ditugaskan (satu aset fisik tidak mungkin
 * dipegang dua pegawai sekaligus). Riwayat penugasan APPEND-ONLY
 * (baris baru per penugasan, bukan update in-place) supaya jejak
 * "siapa pernah pegang aset ini" tetap utuh.
 */
final class AssignAsset
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $assetId, string $employeeId, string $assignedByEmployeeId, ?string $notes, AuditActor $actor): string
    {
        return DB::transaction(function () use ($assetId, $employeeId, $assignedByEmployeeId, $notes, $actor) {
            $asset = DB::table('ast_assets')->where('id', $assetId)->lockForUpdate()->first();

            if ($asset === null) {
                throw new DomainException('Aset tidak ditemukan.');
            }

            if ($asset->status !== 'tersedia') {
                throw new DomainException("Aset \"{$asset->name}\" sedang tidak tersedia untuk ditugaskan (status: {$asset->status}).");
            }

            $id = (string) Uuid7::generate();
            $now = new DateTimeImmutable;

            DB::table('ast_assignments')->insert([
                'id' => $id,
                'asset_id' => $assetId,
                'employee_id' => $employeeId,
                'assigned_at' => $now,
                'assigned_by' => $assignedByEmployeeId,
                'returned_at' => null,
                'returned_condition' => null,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            DB::table('ast_assets')->where('id', $assetId)->update([
                'status' => 'dipakai',
                'updated_at' => $now,
                'version' => $asset->version + 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'ast_assignment',
                auditableId: $id,
                action: AuditAction::Created,
                newValues: ['asset_id' => $assetId, 'employee_id' => $employeeId],
            ));

            return $id;
        });
    }
}
