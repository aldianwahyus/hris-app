<?php

declare(strict_types=1);

namespace App\Modules\Shift\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mengajukan tukar shift dari layar ESS — rekan kerja TIDAK dimintai
 * konfirmasi terpisah (keputusan bisnis), langsung ke antrean Atasan
 * Langsung (1 tahap, lihat ShiftSwapApprovalController). Workflow
 * Engine di sini HANYA untuk pelacakan SLA/pengingat, bukan penentu
 * approver (pola sama SubmitLeaveRequest/SubmitOvertimeRequest).
 */
final class SubmitShiftSwapRequest
{
    public function __construct(
        private readonly WorkflowInstanceRepository $workflow,
        private readonly AuditRepository $audit,
    ) {}

    public function handle(
        string $requestingEmployeeId,
        string $counterpartEmployeeId,
        DateTimeImmutable $swapDate,
        ?string $reason,
        AuditActor $actor,
    ): string {
        if ($requestingEmployeeId === $counterpartEmployeeId) {
            throw new InvalidArgumentException('Tidak dapat mengajukan tukar shift dengan diri sendiri.');
        }

        $today = new DateTimeImmutable('today');
        if ($swapDate < $today) {
            throw new InvalidArgumentException('Tanggal tukar shift tidak boleh di masa lalu.');
        }

        return DB::transaction(function () use ($requestingEmployeeId, $counterpartEmployeeId, $swapDate, $reason, $actor) {
            $requestingPatternId = $this->currentPatternIdFor($requestingEmployeeId, $swapDate);
            $counterpartPatternId = $this->currentPatternIdFor($counterpartEmployeeId, $swapDate);

            if ($requestingPatternId === null) {
                throw new InvalidArgumentException('Anda tidak memiliki penugasan shift pada tanggal itu.');
            }

            if ($counterpartPatternId === null) {
                throw new InvalidArgumentException('Rekan yang dituju tidak memiliki penugasan shift pada tanggal itu.');
            }

            $now = new DateTimeImmutable;
            $requestId = (string) Uuid7::generate();

            DB::table('shf_swap_requests')->insert([
                'id' => $requestId,
                'request_number' => $this->nextRequestNumber($now),
                'requesting_employee_id' => $requestingEmployeeId,
                'counterpart_employee_id' => $counterpartEmployeeId,
                'swap_date' => $swapDate->format('Y-m-d'),
                'requesting_original_pattern_id' => $requestingPatternId,
                'counterpart_original_pattern_id' => $counterpartPatternId,
                'reason' => $reason,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $instance = $this->workflow->startFor('shift_swap_request', $requestId, $requestingEmployeeId, $now);

            DB::table('shf_swap_requests')->where('id', $requestId)->update(['wf_instance_id' => $instance->id]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'shift_swap_request',
                auditableId: $requestId,
                action: AuditAction::Submitted,
                newValues: [
                    'counterpart_employee_id' => $counterpartEmployeeId,
                    'swap_date' => $swapDate->format('Y-m-d'),
                ],
            ));

            return DB::table('shf_swap_requests')->where('id', $requestId)->value('request_number');
        });
    }

    private function currentPatternIdFor(string $employeeId, DateTimeImmutable $date): ?string
    {
        $dateString = $date->format('Y-m-d');

        return DB::table('shf_employee_assignments')
            ->where('employee_id', $employeeId)
            ->where('effective_from', '<=', $dateString)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $dateString))
            ->value('shift_pattern_id');
    }

    private function nextRequestNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('TS/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('shf_swap_requests')
            ->where('request_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
