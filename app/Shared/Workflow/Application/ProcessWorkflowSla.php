<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use App\Shared\Workflow\Domain\SlaWindow;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Mitigasi risiko RA-3 (ARCH-001 §10): persetujuan lembur terpusat pada
 * satu pejabat, sementara keterlambatan menghanguskan hak bayar pegawai
 * yang sudah bekerja. Dijalankan berkala oleh penjadwal — lihat
 * App\Console\Commands\ProcessSlaReminders.
 *
 * Tugasnya MURNI mekanika Workflow Engine: mengevaluasi SlaWindow,
 * menandai kedaluwarsa, melepas kuota lembur mingguan, mencatat audit
 * trail. Siapa yang diberi tahu (resolusi audiens, pengiriman
 * notifikasi) SENGAJA tidak dilakukan di sini — itu butuh Contracts
 * milik modul Access/Employee, dan Shared TIDAK BOLEH bergantung pada
 * modul bisnis (ModuleBoundaryTest). Pemanggil (Command) yang
 * mengorkestrasi keduanya.
 */
final class ProcessWorkflowSla
{
    /** Peta document_type (kosakata Workflow Engine) → auditable_type (kosakata audit trail pengajuan). */
    private const AUDITABLE_TYPES = [
        'leave_request' => 'leave_request',
        'overtime_request' => 'ovt_request',
    ];

    public function __construct(
        private readonly WorkflowInstanceRepository $workflow,
        private readonly AuditRepository $audit,
    ) {}

    public function run(DateTimeImmutable $now): WorkflowSlaReport
    {
        $reminders = [];
        $expired = [];

        $pendingSteps = DB::table('wf_instance_steps as s')
            ->join('wf_instances as i', 'i.id', '=', 's.instance_id')
            ->join('wf_steps as st', 'st.id', '=', 's.step_id')
            ->where('s.status', 'pending')
            ->where('i.status', 'pending')
            ->whereNotNull('s.sla_due_at')
            ->select(
                's.id as instance_step_id', 's.instance_id', 's.started_at', 's.sla_due_at', 's.reminders_sent',
                'st.reminder_days_before',
                'i.document_type', 'i.document_id',
            )
            ->get();

        foreach ($pendingSteps as $row) {
            /** @var stdClass $row */
            $requestNumber = $this->requestNumberFor($row->document_type, $row->document_id);

            if ($requestNumber === null) {
                continue; // data tidak konsisten — jangan proses lebih lanjut
            }

            $window = new SlaWindow(
                startedAt: new DateTimeImmutable($row->started_at),
                dueAt: new DateTimeImmutable($row->sla_due_at),
                reminderDaysBefore: $row->reminder_days_before === null
                    ? [7, 3]
                    : json_decode((string) $row->reminder_days_before, true),
            );

            if ($window->isOverdue($now)) {
                $expiredEntry = $this->expireInstance($row, $now, $requestNumber);

                if ($expiredEntry !== null) {
                    $expired[] = $expiredEntry;
                }

                continue; // kedaluwarsa tidak perlu pengingat lagi
            }

            $alreadySent = $row->reminders_sent === null ? [] : json_decode((string) $row->reminders_sent, true);
            $threshold = $window->shouldRemindNow($now, $alreadySent);

            if ($threshold === null) {
                continue;
            }

            DB::table('wf_instance_steps')->where('id', $row->instance_step_id)->update([
                'reminders_sent' => json_encode([...$alreadySent, $threshold]),
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: AuditActor::system('sla-scheduler'),
                auditableType: self::AUDITABLE_TYPES[$row->document_type] ?? $row->document_type,
                auditableId: $row->document_id,
                action: AuditAction::Reminded,
                newValues: ['threshold_hari' => $threshold],
                contextRef: $requestNumber,
            ));

            $reminders[] = new ReminderDue($row->document_type, $row->document_id, $row->instance_id, $threshold, $requestNumber);
        }

        return new WorkflowSlaReport($reminders, $expired);
    }

    private function expireInstance(stdClass $row, DateTimeImmutable $now, string $requestNumber): ?InstanceExpired
    {
        $instance = $this->workflow->findPending($row->instance_id);

        if ($instance === null) {
            return null; // sudah diputus/diproses proses lain sejak dibaca
        }

        $instance->expire($now, 'Tenggat SLA terlewati tanpa keputusan.');
        $this->workflow->save($instance);
        $instance->releaseEvents(); // WorkflowExpired — audit trail ditulis langsung di bawah

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: AuditActor::system('sla-scheduler'),
            auditableType: self::AUDITABLE_TYPES[$row->document_type] ?? $row->document_type,
            auditableId: $row->document_id,
            action: AuditAction::Expired,
            contextRef: $requestNumber,
        ));

        $this->markDocumentExpired($row->document_type, $row->document_id);

        if ($row->document_type === 'overtime_request') {
            $this->releaseWeeklyOvertimeQuota($row->document_id);
        } elseif ($row->document_type === 'leave_request') {
            $this->releaseLeaveBalance($row->document_id);
        }

        return new InstanceExpired($row->document_type, $row->document_id, $row->instance_id, $requestNumber);
    }

    private function requestNumberFor(string $documentType, string $documentId): ?string
    {
        return match ($documentType) {
            'leave_request' => DB::table('leave_requests')->where('id', $documentId)->value('request_number'),
            'overtime_request' => DB::table('ovt_requests')->where('id', $documentId)->value('spkl_number'),
            default => null,
        };
    }

    private function markDocumentExpired(string $documentType, string $documentId): void
    {
        $table = match ($documentType) {
            'leave_request' => 'leave_requests',
            'overtime_request' => 'ovt_requests',
            default => null,
        };

        if ($table === null) {
            return;
        }

        // 'pending_pimpinan' HANYA berlaku untuk overtime_request (tahap 2
        // Lembur, lihat ApprovalQueueController) — disertakan di sini agar
        // pengajuan yang sudah lolos Atasan Langsung tapi mandek di
        // Pimpinan Kantor TETAP bisa kedaluwarsa (RA-3), bukan terkunci
        // pending_pimpinan selamanya. Tidak berdampak ke leave_requests
        // (status itu tidak pernah muncul di sana).
        DB::table($table)->where('id', $documentId)->whereIn('status', ['pending', 'pending_pimpinan'])->update([
            'status' => 'expired',
            'updated_at' => now(),
        ]);
    }

    /**
     * Melepas jam yang dipesan (pending_hours) pada kuota mingguan —
     * pengajuan yang kedaluwarsa tanpa keputusan tidak boleh terus
     * mengunci plafon 18 jam/minggu pegawai (DEC-32).
     */
    private function releaseWeeklyOvertimeQuota(string $ovtRequestId): void
    {
        $request = DB::table('ovt_requests')->where('id', $ovtRequestId)->first();

        if ($request === null || $request->planned_hours === null) {
            return;
        }

        // Baris kuota ditemukan lewat rentang minggu (Senin s.d. Minggu)
        // yang menaungi work_date — bukan menghitung ulang harinya di
        // sini, agar tidak menduplikasi aturan App\Modules\Overtime\Domain
        // (Shared tidak boleh bergantung pada modul bisnis).
        $sevenDaysBefore = (new DateTimeImmutable($request->work_date))->modify('-7 days')->format('Y-m-d');

        DB::table('ovt_weekly_quotas')
            ->where('employee_id', $request->employee_id)
            ->where('week_start_date', '<=', $request->work_date)
            ->where('week_start_date', '>', $sevenDaysBefore)
            ->decrement('pending_hours', (float) $request->planned_hours);
    }

    /**
     * Mengembalikan hari cuti yang sudah terpotong saat pengajuan
     * (SubmitLeaveRequest mendebit leave_balances.used_days SEBELUM
     * persetujuan, mencegah pemesanan ganda) — tanpa ini, pengajuan yang
     * kedaluwarsa tanpa keputusan menghanguskan jatah cuti pegawai
     * secara PERMANEN (bug ditemukan lewat audit kode). Membalik PERSIS
     * snapshot rencana debit (leave_requests.bucket_debits, diisi
     * SubmitLeaveRequest) lewat SQL mentah — TIDAK memanggil
     * App\Modules\Leave\Application\ReleaseLeaveBalance (Shared tidak
     * boleh bergantung pada modul bisnis, sama seperti
     * releaseWeeklyOvertimeQuota() di atas tidak memanggil modul Overtime).
     */
    private function releaseLeaveBalance(string $leaveRequestId): void
    {
        $request = DB::table('leave_requests')->where('id', $leaveRequestId)->first();

        if ($request === null || $request->bucket_debits === null) {
            return;
        }

        $year = (int) date('Y', strtotime((string) $request->start_date));
        $debits = json_decode((string) $request->bucket_debits, true) ?? [];

        foreach ($debits as $debit) {
            DB::table('leave_balances')
                ->where('employee_id', $request->employee_id)
                ->where('year', $year)
                ->where('bucket_type', $debit['bucket_type'])
                ->decrement('used_days', (float) $debit['days']);
        }

        DB::table('leave_requests')->where('id', $leaveRequestId)->update(['bucket_debits' => null]);
    }
}
