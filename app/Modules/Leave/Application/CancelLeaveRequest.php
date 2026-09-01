<?php

declare(strict_types=1);

namespace App\Modules\Leave\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Batal ajukan sendiri — HANYA boleh selama status masih 'pending'
 * (SEBELUM tahap 1/Atasan Langsung memutus, sesuai konfirmasi user).
 * Begitu sudah 'pending_pimpinan' atau lebih lanjut, pegawai harus
 * minta atasan/pimpinan MENOLAK secara manual — bukan lagi kewenangan
 * pemohon sendiri.
 */
final class CancelLeaveRequest
{
    public function __construct(
        private readonly ReleaseLeaveBalance $releaseBalance,
        private readonly AuditRepository $audit,
    ) {}

    public function handle(string $leaveRequestId, string $employeeId, AuditActor $actor): void
    {
        DB::transaction(function () use ($leaveRequestId, $employeeId, $actor) {
            $request = DB::table('leave_requests')
                ->where('id', $leaveRequestId)
                ->lockForUpdate()
                ->first();

            if ($request === null || $request->employee_id !== $employeeId) {
                throw new DomainException('Pengajuan cuti tidak ditemukan.');
            }

            if ($request->status !== 'pending') {
                throw new DomainException('Pengajuan yang sudah diproses tidak dapat dibatalkan sendiri — hubungi atasan Anda.');
            }

            DB::table('leave_requests')->where('id', $leaveRequestId)->update([
                'status' => 'cancelled',
                'updated_at' => new DateTimeImmutable,
            ]);

            $this->releaseBalance->handle($leaveRequestId);

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'leave_request',
                auditableId: $leaveRequestId,
                action: AuditAction::Cancelled,
                newValues: ['status' => 'cancelled'],
                contextRef: $request->request_number,
            ));
        });
    }
}
