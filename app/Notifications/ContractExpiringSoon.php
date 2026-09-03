<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengingat kontrak (kontrak/outsource) akan berakhir — ke hr_admin
 * kantor pegawai + hr_approver bank-wide (lihat
 * App\Console\Commands\CheckExpiringContracts), pola SAMA
 * ApprovalSlaReminder tapi ke HC/pemutus kebijakan, BUKAN pemohon.
 */
final class ContractExpiringSoon extends Notification
{
    public function __construct(
        public readonly string $employeeName,
        public readonly string $contractNumber,
        public readonly string $endDate,
        public readonly int $daysRemaining,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Kontrak {$this->contractNumber} akan berakhir dalam {$this->daysRemaining} hari")
            ->line("Kontrak {$this->contractNumber} milik {$this->employeeName} akan berakhir pada {$this->endDate}.")
            ->line('Segera tindak lanjuti: perpanjang atau proses pemutusan kontrak.')
            ->action('Buka Data Pegawai', url('/admin/sistem/pegawai'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'contract_number' => $this->contractNumber,
            'employee_name' => $this->employeeName,
            'end_date' => $this->endDate,
            'message' => "Kontrak {$this->contractNumber} milik {$this->employeeName} akan berakhir dalam {$this->daysRemaining} hari ({$this->endDate}).",
        ];
    }
}
