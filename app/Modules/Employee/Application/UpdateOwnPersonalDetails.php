<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Modules\Employee\Domain\SelfEditableEmployeeField;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pegawai mengubah data PRIBADI miliknya sendiri ("CV Saya") — TANPA
 * maker-checker (beda dari SubmitEmployeeProfileChange yang untuk data
 * ORGANISASI): risikonya rendah, bukan proses bisnis SDM, jadi
 * langsung diterapkan. Tetap tercatat di audit trail supaya siapa
 * mengubah apa kapan tetap terlacak walau tanpa persetujuan.
 */
final class UpdateOwnPersonalDetails
{
    public function __construct(private readonly AuditRepository $audit) {}

    /** @param  array<string, mixed>  $changes */
    public function handle(string $employeeId, array $changes, AuditActor $actor): void
    {
        $current = DB::table('emp_employees')->where('id', $employeeId)->first();

        if ($current === null) {
            throw new RuntimeException("Pegawai (id: {$employeeId}) tidak ditemukan.");
        }

        if ($changes === []) {
            throw new InvalidArgumentException('Tidak ada perubahan yang diajukan.');
        }

        foreach (array_keys($changes) as $field) {
            if (SelfEditableEmployeeField::tryFrom($field) === null) {
                throw new InvalidArgumentException("Field \"{$field}\" tidak boleh diubah lewat CV Saya.");
            }
        }

        $oldValues = [];
        foreach (array_keys($changes) as $field) {
            $oldValues[$field] = $current->{$field} ?? null;
        }

        DB::transaction(function () use ($employeeId, $changes, $oldValues, $current, $actor) {
            $now = new DateTimeImmutable;

            DB::table('emp_employees')
                ->where('id', $employeeId)
                ->where('version', $current->version)
                ->update([...$changes, 'updated_at' => $now, 'version' => $current->version + 1]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'employee_personal_details',
                auditableId: $employeeId,
                action: AuditAction::Updated,
                oldValues: $oldValues,
                newValues: $changes,
            ));
        });
    }
}
