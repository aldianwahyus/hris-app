<?php

declare(strict_types=1);

namespace App\Modules\Helpdesk\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan tiket Helpdesk dari layar ESS — nomor tiket dibangkitkan
 * per bulan (pola SAMA nextRequestNumber di SubmitLeaveRequest),
 * status awal selalu 'terbuka'.
 */
final class SubmitTicket
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(
        string $employeeId,
        string $category,
        string $subject,
        string $description,
        string $priority,
        AuditActor $actor,
    ): string {
        return DB::transaction(function () use ($employeeId, $category, $subject, $description, $priority, $actor) {
            $now = new DateTimeImmutable;
            $id = (string) Uuid7::generate();

            DB::table('hd_tickets')->insert([
                'id' => $id,
                'ticket_number' => $this->nextTicketNumber($now),
                'employee_id' => $employeeId,
                'category' => $category,
                'subject' => $subject,
                'description' => $description,
                'status' => 'terbuka',
                'priority' => $priority,
                'assigned_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'hd_ticket',
                auditableId: $id,
                action: AuditAction::Created,
                newValues: ['category' => $category, 'subject' => $subject, 'priority' => $priority],
            ));

            return $id;
        });
    }

    private function nextTicketNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('TIKET/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('hd_tickets')
            ->where('ticket_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
