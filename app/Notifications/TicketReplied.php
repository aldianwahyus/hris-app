<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu pihak LAWAN (pegawai atau HC) bahwa ada balasan baru
 * pada satu tiket Helpdesk — dua arah, dikirim dari HelpdeskController
 * (pegawai membalas → notifikasi ke HC yang ditugaskan) maupun
 * HelpdeskQueueController (HC membalas → notifikasi ke pegawai
 * pengaju). Pola SAMA RequestDecided (mail+database, toArray()
 * generik).
 */
final class TicketReplied extends Notification
{
    public function __construct(
        public readonly string $ticketNumber,
        public readonly string $subject,
        public readonly string $replierName,
        public readonly string $message,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Balasan baru tiket {$this->ticketNumber}: {$this->subject}")
            ->line($this->composedMessage());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_number' => $this->ticketNumber,
            'subject' => $this->subject,
            'message' => $this->composedMessage(),
        ];
    }

    private function composedMessage(): string
    {
        return "{$this->replierName} membalas tiket {$this->ticketNumber} ({$this->subject}): {$this->message}";
    }
}
