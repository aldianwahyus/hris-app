<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus satu SK — TIDAK menyentuh/membatalkan
 * profile_change_request_id yang mungkin sudah tertaut (kalau ada,
 * pengajuan perubahan data induknya tetap berjalan independen, sudah
 * tercatat sendiri di jejak auditnya).
 */
final class RemoveDecisionLetter
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $id, AuditActor $actor): void
    {
        DB::transaction(function () use ($id, $actor) {
            $row = DB::table('emp_decision_letters')->where('id', $id)->first();

            if ($row === null) {
                return;
            }

            DB::table('emp_decision_letters')->where('id', $id)->delete();

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'employee_decision_letter',
                auditableId: $id,
                action: AuditAction::Deleted,
                oldValues: [
                    'sk_type' => $row->sk_type,
                    'sk_number' => $row->sk_number,
                    'employee_id' => $row->employee_id,
                ],
            ));
        });
    }
}
