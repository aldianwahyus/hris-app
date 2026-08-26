<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Access\Application\ResolveApprovalAudience;
use App\Notifications\ApprovalSlaExpired;
use App\Notifications\ApprovalSlaReminder;
use App\Shared\Workflow\Application\InstanceExpired;
use App\Shared\Workflow\Application\ProcessWorkflowSla;
use App\Shared\Workflow\Application\ReminderDue;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Mitigasi risiko RA-3 (ARCH-001 §10): persetujuan lembur terpusat pada
 * satu pejabat, sementara keterlambatan menghanguskan hak bayar pegawai.
 *
 * Mengorkestrasi dua kepentingan yang secara arsitektur harus terpisah:
 * mekanika Workflow Engine (ProcessWorkflowSla — Shared, tidak bergantung
 * modul bisnis) dan resolusi siapa yang diberi tahu (ResolveApprovalAudience
 * — butuh Contracts Access + Employee). Command ini adalah satu-satunya
 * tempat keduanya boleh bertemu.
 */
final class ProcessSlaReminders extends Command
{
    protected $signature = 'workflow:process-sla';

    protected $description = 'Kirim pengingat H-7/H-3, tandai pengajuan kedaluwarsa, dan lepas kuota lembur mingguan.';

    public function __construct(
        private readonly ProcessWorkflowSla $processor,
        private readonly ResolveApprovalAudience $audience,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->processor->run(new DateTimeImmutable);

        foreach ($report->reminders as $reminder) {
            $this->notify($reminder, fn ($recipients) => Notification::send(
                $recipients,
                new ApprovalSlaReminder($reminder->requestNumber, $reminder->thresholdDays, $reminder->documentType)
            ));
        }

        foreach ($report->expired as $expiredEntry) {
            $this->notify($expiredEntry, fn ($recipients) => Notification::send(
                $recipients,
                new ApprovalSlaExpired($expiredEntry->requestNumber, $expiredEntry->documentType)
            ));
        }

        $this->info(sprintf(
            'Pengingat terkirim: %d · Pengajuan kedaluwarsa: %d',
            count($report->reminders),
            count($report->expired),
        ));

        return self::SUCCESS;
    }

    private function notify(ReminderDue|InstanceExpired $entry, callable $send): void
    {
        $employeeId = $this->ownerEmployeeId($entry->documentType, $entry->documentId);

        if ($employeeId === null) {
            return;
        }

        $recipients = $this->audience->forDocument($entry->documentType, $entry->documentId, $employeeId);

        if ($recipients === []) {
            return;
        }

        $send($recipients);
    }

    private function ownerEmployeeId(string $documentType, string $documentId): ?string
    {
        return match ($documentType) {
            'leave_request' => DB::table('leave_requests')->where('id', $documentId)->value('employee_id'),
            'overtime_request' => DB::table('ovt_requests')->where('id', $documentId)->value('employee_id'),
            default => null,
        };
    }
}
