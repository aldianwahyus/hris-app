<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Access\Contracts\UserDirectory;
use App\Notifications\ContractExpiringSoon;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Pengingat kontrak (kontrak/outsource) akan berakhir — Manajemen
 * Kontrak (evaluasi PM/client 2026-09-02), pola SAMA
 * ProcessSlaReminders TAPI disederhanakan: SATU ambang saja (bukan
 * H-7/H-3 berjenjang, karena ini bukan proses persetujuan berjenjang)
 * + `reminder_sent_at` mencegah kirim ganda (bukan array
 * reminders_sent seperti wf_instance_steps).
 */
final class CheckExpiringContracts extends Command
{
    protected $signature = 'contracts:check-expiring';

    protected $description = 'Kirim pengingat kontrak kontrak/outsource yang akan berakhir ke hr_admin kantor terkait + hr_approver.';

    public function __construct(
        private readonly ParameterResolver $parameters,
        private readonly UserDirectory $users,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = new DateTimeImmutable('today');
        $asOf = AsOfDate::on($today);
        $thresholdDays = $this->parameters->integer('CONTRACT_EXPIRY_REMINDER_DAYS', $asOf);
        $windowEnd = $today->modify("+{$thresholdDays} days")->format('Y-m-d');

        $contracts = DB::table('emp_contracts as c')
            ->join('emp_employees as e', 'e.id', '=', 'c.employee_id')
            ->where('c.status', 'aktif')
            ->whereNull('c.reminder_sent_at')
            ->whereDate('c.end_date', '<=', $windowEnd)
            ->whereDate('c.end_date', '>=', $today->format('Y-m-d'))
            ->select('c.id', 'c.contract_number', 'c.end_date', 'e.full_name', 'e.office_id')
            ->get();

        $hrApproverIds = $this->users->userIdsWithRole('hr_approver');
        $hrAdminIds = $this->users->userIdsWithRole('hr_admin');
        $hrApprovers = User::query()->whereIn('id', $hrApproverIds)->get();

        $sent = 0;

        foreach ($contracts as $contract) {
            $daysRemaining = $today->diff(new DateTimeImmutable($contract->end_date))->days;
            $officeHrAdmins = User::query()->whereIn('id', $hrAdminIds)
                ->whereHas('employee', fn ($q) => $q->where('office_id', $contract->office_id))
                ->get();

            $recipients = $hrApprovers->merge($officeHrAdmins)->unique('id');

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ContractExpiringSoon(
                    employeeName: $contract->full_name,
                    contractNumber: $contract->contract_number,
                    endDate: $contract->end_date,
                    daysRemaining: (int) $daysRemaining,
                ));
            }

            DB::table('emp_contracts')->where('id', $contract->id)->update(['reminder_sent_at' => now()]);
            $sent++;
        }

        $this->info("Pengingat kontrak terkirim: {$sent}");

        return self::SUCCESS;
    }
}
