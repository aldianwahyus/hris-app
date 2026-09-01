<?php

declare(strict_types=1);

namespace App\Modules\Shift\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Batal ajukan sendiri — pola SAMA PERSIS
 * App\Modules\Leave\Application\CancelLeaveRequest. Tukar Shift SATU
 * tahap saja, dan penugasan shift belum pernah benar-benar ditukar
 * selama status masih 'pending' (ApplyShiftSwap baru berjalan saat
 * disetujui) — jadi cukup ubah status, tidak ada efek samping lain
 * yang perlu dibalik.
 */
final class CancelShiftSwapRequest
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $shiftSwapRequestId, string $employeeId, AuditActor $actor): void
    {
        DB::transaction(function () use ($shiftSwapRequestId, $employeeId, $actor) {
            $request = DB::table('shf_swap_requests')
                ->where('id', $shiftSwapRequestId)
                ->lockForUpdate()
                ->first();

            if ($request === null || $request->requesting_employee_id !== $employeeId) {
                throw new DomainException('Pengajuan tukar shift tidak ditemukan.');
            }

            if ($request->status !== 'pending') {
                throw new DomainException('Pengajuan yang sudah diproses tidak dapat dibatalkan sendiri — hubungi atasan Anda.');
            }

            DB::table('shf_swap_requests')->where('id', $shiftSwapRequestId)->update([
                'status' => 'cancelled',
                'updated_at' => new DateTimeImmutable,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'shf_swap_request',
                auditableId: $shiftSwapRequestId,
                action: AuditAction::Cancelled,
                newValues: ['status' => 'cancelled'],
                contextRef: $request->request_number,
            ));
        });
    }
}
