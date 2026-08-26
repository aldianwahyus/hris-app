<?php

declare(strict_types=1);

namespace App\Shared\Audit\Domain;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Satu entri audit trail — bersifat IMUTABEL.
 *
 * Tidak ada setter dan tidak ada operasi ubah. Ini cerminan sifat
 * append-only pada tingkat basis data (REVOKE UPDATE, DELETE).
 * Koreksi dilakukan dengan menambah entri baru, bukan mengubah yang lama.
 *
 * Merekam: siapa, apa, kapan, nilai sebelum, nilai sesudah, dari mana,
 * dan atas dasar apa (DB-001 §3.1).
 */
final readonly class AuditEntry
{
    public string $id;

    public function __construct(
        public DateTimeImmutable $occurredAt,
        public AuditActor $actor,
        public string $auditableType,
        public string $auditableId,
        public AuditAction $action,
        public ?array $oldValues = null,
        public ?array $newValues = null,
        public ?string $contextRef = null,
    ) {
        if ($auditableType === '') {
            throw new InvalidArgumentException('auditableType tidak boleh kosong.');
        }

        if ($action->requiresContext() && $contextRef === null) {
            throw new InvalidArgumentException(sprintf(
                'Tindakan "%s" wajib mencantumkan dasar (contextRef), mis. nomor SPKL atau SK.',
                $action->value
            ));
        }

        $this->id = (string) Uuid7::generate();
    }

    /** Kolom yang benar-benar berubah — memudahkan penelusuran auditor. */
    public function changedFields(): array
    {
        if ($this->oldValues === null || $this->newValues === null) {
            return array_keys($this->newValues ?? $this->oldValues ?? []);
        }

        $changed = [];

        foreach ($this->newValues as $field => $newValue) {
            $oldValue = $this->oldValues[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s.uP'),
            'actor_id' => $this->actor->actorId,
            'actor_role' => $this->actor->actorRole,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'action' => $this->action->value,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'ip_address' => $this->actor->ipAddress,
            'user_agent' => $this->actor->userAgent,
            'context_ref' => $this->contextRef,
        ];
    }
}
