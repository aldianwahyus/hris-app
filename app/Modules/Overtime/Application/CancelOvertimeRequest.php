<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Batal ajukan sendiri — pola SAMA PERSIS
 * App\Modules\Leave\Application\CancelLeaveRequest: HANYA boleh selama
 * status masih 'pending' (SEBELUM tahap 1/Atasan Langsung memutus).
 */
final class CancelOvertimeRequest
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $overtimeRequestId, string $employeeId, AuditActor $actor): void
    {
        DB::transaction(function () use ($overtimeRequestId, $employeeId, $actor) {
            $request = DB::table('ovt_requests')
                ->where('id', $overtimeRequestId)
                ->lockForUpdate()
                ->first();

            if ($request === null || $request->employee_id !== $employeeId) {
                throw new DomainException('Pengajuan lembur tidak ditemukan.');
            }

            if ($request->status !== 'pending') {
                throw new DomainException('Pengajuan yang sudah diproses tidak dapat dibatalkan sendiri — hubungi atasan Anda.');
            }

            DB::table('ovt_requests')->where('id', $overtimeRequestId)->update([
                'status' => 'cancelled',
                'updated_at' => new DateTimeImmutable,
            ]);

            // Melepas jam yang dipesan (pending_hours) — pola SAMA PERSIS
            // ApprovalQueueController::decide() jalur tolak dan
            // ProcessWorkflowSla::releaseWeeklyOvertimeQuota() jalur
            // kedaluwarsa (Shared, sengaja tidak memanggil kelas ini —
            // lihat komentar di sana).
            if ($request->planned_hours !== null) {
                $sevenDaysBefore = (new DateTimeImmutable($request->work_date))->modify('-7 days')->format('Y-m-d');

                DB::table('ovt_weekly_quotas')
                    ->where('employee_id', $request->employee_id)
                    ->where('week_start_date', '<=', $request->work_date)
                    ->where('week_start_date', '>', $sevenDaysBefore)
                    ->decrement('pending_hours', (float) $request->planned_hours);
            }

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'ovt_request',
                auditableId: $overtimeRequestId,
                action: AuditAction::Cancelled,
                newValues: ['status' => 'cancelled'],
                contextRef: $request->spkl_number,
            ));
        });
    }
}
