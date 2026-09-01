<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu PEMOHON hasil keputusan atas pengajuannya sendiri —
 * SEBELUM notifikasi ini dibuat, satu-satunya notifikasi yang pernah
 * dikirim aplikasi ini adalah ApprovalSlaReminder/ApprovalSlaExpired
 * (ke PEMUTUS soal tenggat, bukan ke pemohon soal hasil). Pola SAMA
 * PERSIS ApprovalSlaReminder (via mail+database, toArray() untuk
 * NotificationApiController mobile & lonceng web).
 *
 * SENGAJA hanya SATU field `message` sudah jadi (bukan field
 * `reason` terpisah) — supaya NotificationScreen.tsx mobile yang
 * SUDAH ADA (merender data.message apa adanya) tidak perlu diubah
 * sama sekali untuk menampilkan alasan penolakan.
 */
final class RequestDecided extends Notification
{
    public function __construct(
        public readonly string $requestNumber,
        public readonly string $documentTypeLabel,
        public readonly bool $approved,
        public readonly ?string $reason = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject("Pengajuan {$this->requestNumber}: {$this->statusLabel()}");
        $message->line($this->composedMessage());

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'request_number' => $this->requestNumber,
            'document_type' => $this->documentTypeLabel,
            'message' => $this->composedMessage(),
        ];
    }

    private function statusLabel(): string
    {
        return $this->approved ? 'Disetujui' : 'Ditolak';
    }

    private function composedMessage(): string
    {
        $inti = "Pengajuan {$this->documentTypeLabel} {$this->requestNumber} Anda ".mb_strtoupper($this->statusLabel()).'.';

        if (! $this->approved && $this->reason !== null && $this->reason !== '') {
            $inti .= " Alasan: {$this->reason}";
        }

        return $inti;
    }
}
