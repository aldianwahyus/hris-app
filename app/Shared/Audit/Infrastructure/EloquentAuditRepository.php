<?php

declare(strict_types=1);

namespace App\Shared\Audit\Infrastructure;

use App\Shared\Audit\Domain\AccessLogEntry;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Implementasi penyimpanan audit di atas PostgreSQL.
 *
 * Sengaja memakai query builder, bukan Eloquent Model: tabel audit
 * bersifat append-only dan terpartisi. Model Eloquent membawa serta
 * kemampuan save()/delete() yang justru ingin kita cegah, dan event
 * model-nya menambah beban tanpa manfaat pada tabel yang hanya ditulis.
 */
final class EloquentAuditRepository implements AuditRepository
{
    private const CHANGE_TABLE = 'aud_change_logs';

    private const ACCESS_TABLE = 'aud_access_logs';

    public function append(AuditEntry $entry): void
    {
        DB::table(self::CHANGE_TABLE)->insert($this->toRow($entry));
    }

    /** @param array<int, AuditEntry> $entries */
    public function appendMany(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        // Sisipan massal: satu perintah untuk banyak baris. Penting saat
        // proses batch (mis. payroll run) menghasilkan ribuan entri.
        DB::table(self::CHANGE_TABLE)->insert(
            array_map(fn (AuditEntry $entry) => $this->toRow($entry), $entries)
        );
    }

    public function appendAccess(AccessLogEntry $entry): void
    {
        DB::table(self::ACCESS_TABLE)->insert($entry->toArray());
    }

    /** @return array<int, AuditEntry> */
    public function findForAuditable(string $auditableType, string $auditableId): array
    {
        $rows = DB::table(self::CHANGE_TABLE)
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->orderByDesc('occurred_at')
            ->get();

        return $rows->map(fn ($row) => $this->toEntry($row))->all();
    }

    /** @return array<int, AuditEntry> */
    public function findByActor(string $actorId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = DB::table(self::CHANGE_TABLE)
            ->where('actor_id', $actorId)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->get();

        return $rows->map(fn ($row) => $this->toEntry($row))->all();
    }

    private function toRow(AuditEntry $entry): array
    {
        $row = $entry->toArray();

        $row['old_values'] = $row['old_values'] === null ? null : json_encode($row['old_values']);
        $row['new_values'] = $row['new_values'] === null ? null : json_encode($row['new_values']);

        return $row;
    }

    private function toEntry(object $row): AuditEntry
    {
        return new AuditEntry(
            occurredAt: new DateTimeImmutable($row->occurred_at),
            actor: new AuditActor(
                actorId: $row->actor_id,
                actorRole: $row->actor_role,
                ipAddress: $row->ip_address,
                userAgent: $row->user_agent,
            ),
            auditableType: $row->auditable_type,
            auditableId: $row->auditable_id,
            action: AuditAction::from($row->action),
            oldValues: $row->old_values === null ? null : json_decode($row->old_values, true),
            newValues: $row->new_values === null ? null : json_decode($row->new_values, true),
            contextRef: $row->context_ref,
        );
    }
}
