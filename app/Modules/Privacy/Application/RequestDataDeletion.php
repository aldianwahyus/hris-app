<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Mengajukan permintaan penghapusan data pribadi (UU PDP, Fase 2) — SATU permintaan pending per pegawai. */
final class RequestDataDeletion
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $employeeId, string $reason, AuditActor $actor): string
    {
        $alreadyPending = DB::table('pdp_deletion_requests')
            ->where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            throw new DomainException('Anda sudah memiliki permintaan penghapusan data yang masih menunggu peninjauan.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('pdp_deletion_requests')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => $now,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $actor,
            auditableType: 'pdp_deletion_request',
            auditableId: $id,
            action: AuditAction::Submitted,
        ));

        return $id;
    }
}
