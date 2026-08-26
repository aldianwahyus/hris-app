<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use App\Models\User;
use App\Modules\Access\Domain\Role;
use App\Modules\Access\Domain\SegregationOfDutyPolicy;
use App\Modules\Access\Domain\SegregationOfDutyViolation;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;

/**
 * Penetapan peran adalah tindakan istimewa: harus melewati pemeriksaan
 * pemisahan tugas (§6.3) DAN tercatat di audit trail — penetapan peran
 * yang tidak tercatat berarti tidak dapat diaudit siapa memberi
 * kewenangan apa kepada siapa.
 */
final class AssignRole
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(User $target, Role $role, AuditActor $actor): void
    {
        $currentRoles = array_map(
            static fn (string $name): Role => Role::from($name),
            $target->getRoleNames()->all(),
        );

        if (! (new SegregationOfDutyPolicy)->canAssign($currentRoles, $role)) {
            throw SegregationOfDutyViolation::forRole($role);
        }

        $before = array_map(static fn (Role $r): string => $r->value, $currentRoles);

        $target->assignRole($role->value);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $actor,
            auditableType: 'user_role',
            auditableId: (string) $target->getKey(),
            action: AuditAction::Updated,
            oldValues: ['roles' => $before],
            newValues: ['roles' => [...$before, $role->value]],
        ));
    }
}
