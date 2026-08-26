<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengajuan dinyatakan kedaluwarsa karena tenggat SLA terlewati tanpa
 * keputusan — pada lembur ini berarti hak bayar pegawai HANGUS
 * (mitigasi RA-3, ARCH-001 §10). Dikirim kepada pemutus yang seharusnya
 * memutuskan, sebagai bukti kelalaian tercatat.
 */
final class ApprovalSlaExpired extends Notification
{
    public function __construct(
        public readonly string $requestNumber,
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
        $dampak = $this->documentType === 'overtime_request'
            ? ' Hak bayar pegawai atas pekerjaan ini telah hangus akibat keterlambatan ini.'
            : '';

        return (new MailMessage)
            ->subject("Kedaluwarsa: pengajuan {$this->requestNumber} tidak diputus dalam tenggat SLA")
            ->line("Pengajuan {$jenis} {$this->requestNumber} melewati tenggat SLA tanpa keputusan dan kini berstatus kedaluwarsa.{$dampak}");
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'request_number' => $this->requestNumber,
            'document_type' => $this->documentType,
            'message' => "Pengajuan {$this->requestNumber} kedaluwarsa — tenggat SLA terlewati tanpa keputusan.",
        ];
    }
}
