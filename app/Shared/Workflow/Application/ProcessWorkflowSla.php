<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use App\Shared\Workflow\Domain\InstanceStatus;
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
        'izin_request' => 'izin_request',
        'shift_swap_request' => 'shift_swap_request',
    ];

    /**
     * Peta document_type → [nama tabel, status yang berarti "masih
     * menunggu keputusan"]. Dipakai untuk mendeteksi baris wf_instance_steps
     * yang BASI — sudah diputus lewat jalur approval modul bisnis masing-
     * masing (yang menulis LANGSUNG ke kolom status tabel ini, TIDAK PERNAH
     * memanggil WorkflowInstance::decide()/save()) tapi belum tercermin di
     * sini karena Workflow Engine tidak pernah diberi tahu keputusannya.
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    private const DOCUMENT_STATUS_TABLES = [
        'leave_request' => ['leave_requests', ['pending', 'pending_pimpinan']],
        'overtime_request' => ['ovt_requests', ['pending', 'pending_pimpinan']],
        'izin_request' => ['izin_requests', ['pending']],
        'shift_swap_request' => ['shf_swap_requests', ['pending']],
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

            if (! $this->documentStillPending($row->document_type, $row->document_id)) {
                // Sudah diputus lewat antrean persetujuan modul bisnis
                // (leave_requests/ovt_requests/izin_requests/
                // shf_swap_requests.status) — baris Workflow Engine ini
                // basi, BUKAN benar-benar terlambat. Rekonsiliasi diam-diam
                // TANPA efek samping SLA (tidak melepas saldo cuti/kuota
                // lembur yang sudah benar ditangani jalur keputusan asli,
                // tidak ada entri audit "Expired" palsu pada pengajuan yang
                // sebenarnya sudah disetujui/ditolak) — bug ditemukan lewat
                // audit kode: WorkflowInstance::decide() tidak pernah
                // dipanggil di mana pun, jadi wf_instances/wf_instance_steps
                // tidak pernah tahu keputusan sungguhan sudah terjadi.
                $this->reconcileAlreadyDecided($row);

                continue;
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
            'izin_request' => DB::table('izin_requests')->where('id', $documentId)->value('request_number'),
            'shift_swap_request' => DB::table('shf_swap_requests')->where('id', $documentId)->value('request_number'),
            default => null,
        };
    }

    /** Apakah dokumen bisnis masih dalam status "menunggu keputusan" — lihat DOCUMENT_STATUS_TABLES. */
    private function documentStillPending(string $documentType, string $documentId): bool
    {
        if (! isset(self::DOCUMENT_STATUS_TABLES[$documentType])) {
            return true; // tipe tak dikenal — requestNumberFor() sudah menyaring ini lebih dulu, tidak pernah sampai sini
        }

        [$table, $pendingStatuses] = self::DOCUMENT_STATUS_TABLES[$documentType];

        $status = DB::table($table)->where('id', $documentId)->value('status');

        return in_array($status, $pendingStatuses, true);
    }

    /**
     * Menutup baris wf_instances/wf_instance_steps yang basi — dokumen
     * bisnisnya SUDAH diputus lewat jalurnya sendiri, jadi ini BUKAN
     * kedaluwarsa SLA sungguhan. Update mentah langsung (bukan lewat
     * WorkflowInstance::expire()) SENGAJA: tidak ada peristiwa domain yang
     * benar terjadi di sini untuk direkam, hanya menyamakan bukukan supaya
     * baris ini berhenti muncul di sapuan berikutnya.
     */
    private function reconcileAlreadyDecided(stdClass $row): void
    {
        $now = new DateTimeImmutable;

        DB::table('wf_instance_steps')->where('id', $row->instance_step_id)->update([
            'status' => InstanceStatus::Cancelled->value,
            'completed_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('wf_instances')->where('id', $row->instance_id)->update([
            'status' => InstanceStatus::Cancelled->value,
            'completed_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function markDocumentExpired(string $documentType, string $documentId): void
    {
        $table = match ($documentType) {
            'leave_request' => 'leave_requests',
            'overtime_request' => 'ovt_requests',
            'izin_request' => 'izin_requests',
            'shift_swap_request' => 'shf_swap_requests',
            default => null,
        };

        if ($table === null) {
            return;
        }

        // 'pending_pimpinan' HANYA berlaku untuk overtime_request/
        // leave_request (tahap 2, lihat ApprovalQueueController/
        // LeaveApprovalQueueController) — disertakan di sini agar
        // pengajuan yang sudah lolos Atasan Langsung tapi mandek di
        // Pimpinan Kantor TETAP bisa kedaluwarsa (RA-3), bukan terkunci
        // pending_pimpinan selamanya. Tidak berdampak ke izin_requests/
        // shf_swap_requests (status itu tidak pernah muncul di sana).
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
        // Transaksi + lockForUpdate — TANPA ini, sapuan SLA yang berjalan
        // bersamaan dengan reject manual (LeaveApprovalQueueController)
        // bisa sama-sama membaca bucket_debits non-null sebelum salah satu
        // menuliskan null, lalu keduanya mendekremen used_days: saldo cuti
        // pegawai dikembalikan DUA KALI untuk satu pengajuan yang sama
        // (bug ditemukan lewat audit kode).
        DB::transaction(function () use ($leaveRequestId) {
            $request = DB::table('leave_requests')->where('id', $leaveRequestId)->lockForUpdate()->first();

            if ($request === null || $request->bucket_debits === null) {
                return;
            }

            $year = (int) (new DateTimeImmutable((string) $request->start_date))->format('Y');
            $debits = json_decode((string) $request->bucket_debits, true) ?? [];

            foreach ($debits as $debit) {
                DB::table('leave_balances')
                    ->where('employee_id', $request->employee_id)
                    ->where('year', $year)
                    ->where('bucket_type', $debit['bucket_type'])
                    ->decrement('used_days', (float) $debit['days']);
            }

            DB::table('leave_requests')->where('id', $leaveRequestId)->update(['bucket_debits' => null]);
        });
    }
}
