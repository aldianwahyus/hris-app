<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Batal ajukan sendiri — pola SAMA PERSIS
 * App\Modules\Leave\Application\CancelLeaveRequest. SPPD tidak
 * memotong saldo/kuota apa pun saat diajukan (murni estimasi anggaran,
 * pencairan sungguhan baru terjadi lewat batch terpisah SETELAH
 * disetujui) — jadi cukup ubah status, tidak ada efek samping lain
 * yang perlu dibalik.
 */
final class CancelSppdRequest
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $sppdRequestId, string $employeeId, AuditActor $actor): void
    {
        DB::transaction(function () use ($sppdRequestId, $employeeId, $actor) {
            $request = DB::table('spd_requests')
                ->where('id', $sppdRequestId)
                ->lockForUpdate()
                ->first();

            if ($request === null || $request->employee_id !== $employeeId) {
                throw new DomainException('Pengajuan SPPD tidak ditemukan.');
            }

            if ($request->status !== 'pending') {
                throw new DomainException('Pengajuan yang sudah diproses tidak dapat dibatalkan sendiri — hubungi atasan Anda.');
            }

            DB::table('spd_requests')->where('id', $sppdRequestId)->update([
                'status' => 'cancelled',
                'updated_at' => new DateTimeImmutable,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'spd_request',
                auditableId: $sppdRequestId,
                action: AuditAction::Cancelled,
                newValues: ['status' => 'cancelled'],
                contextRef: $request->request_number,
            ));
        });
    }
}
