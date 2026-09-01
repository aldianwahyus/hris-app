<?php

declare(strict_types=1);

namespace App\Modules\Izin\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Batal ajukan sendiri — pola SAMA PERSIS
 * App\Modules\Leave\Application\CancelLeaveRequest. Izin SATU tahap
 * saja (Atasan Langsung), jadi "sebelum diputus" berarti status masih
 * 'pending' sederhana (tidak ada status transisi pending_pimpinan).
 */
final class CancelIzinRequest
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $izinRequestId, string $employeeId, AuditActor $actor): void
    {
        DB::transaction(function () use ($izinRequestId, $employeeId, $actor) {
            $request = DB::table('izin_requests')
                ->where('id', $izinRequestId)
                ->lockForUpdate()
                ->first();

            if ($request === null || $request->employee_id !== $employeeId) {
                throw new DomainException('Pengajuan izin tidak ditemukan.');
            }

            if ($request->status !== 'pending') {
                throw new DomainException('Pengajuan yang sudah diproses tidak dapat dibatalkan sendiri — hubungi atasan Anda.');
            }

            DB::table('izin_requests')->where('id', $izinRequestId)->update([
                'status' => 'cancelled',
                'updated_at' => new DateTimeImmutable,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'izin_request',
                auditableId: $izinRequestId,
                action: AuditAction::Cancelled,
                newValues: ['status' => 'cancelled'],
                contextRef: $request->request_number,
            ));
        });
    }
}
