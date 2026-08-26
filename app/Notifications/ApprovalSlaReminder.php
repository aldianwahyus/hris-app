<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengingat H-7/H-3 kepada pemutus (mitigasi RA-3 — ARCH-001 §10).
 * Ambang dikirim SEKALI per pengajuan; lihat SlaWindow::shouldRemindNow()
 * dan wf_instance_steps.reminders_sent yang mencegah pengiriman ganda.
 */
final class ApprovalSlaReminder extends Notification
{
    public function __construct(
        public readonly string $requestNumber,
        public readonly int $thresholdDays,
        public readonly string $documentType,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jenis = $this->documentType === 'leave_request' ? 'cuti' : 'lembur';

        $message = (new MailMessage)
            ->subject("Pengingat: pengajuan {$this->requestNumber} menunggu keputusan Anda")
            ->line("Pengajuan {$jenis} {$this->requestNumber} akan melewati tenggat SLA dalam {$this->thresholdDays} hari.")
            ->line('Keterlambatan keputusan menghanguskan hak pegawai yang bersangkutan.');

        if ($this->documentType === 'overtime_request') {
            $message->action('Buka Antrean Persetujuan', url('/persetujuan/lembur'));
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'request_number' => $this->requestNumber,
            'document_type' => $this->documentType,
            'threshold_days' => $this->thresholdDays,
            'message' => "Pengajuan {$this->requestNumber} menunggu keputusan Anda — sisa {$this->thresholdDays} hari sebelum tenggat SLA.",
        ];
    }
}
