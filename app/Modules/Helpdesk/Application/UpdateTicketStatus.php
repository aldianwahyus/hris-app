<?php

declare(strict_types=1);

namespace App\Modules\Helpdesk\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Mengubah status tiket Helpdesk oleh HC — diproses/selesai/ditutup. */
final class UpdateTicketStatus
{
    private const VALID_STATUSES = ['terbuka', 'diproses', 'selesai', 'ditutup'];

    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $ticketId, string $newStatus, AuditActor $actor): void
    {
        if (! in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new DomainException("Status \"{$newStatus}\" tidak dikenal.");
        }

        DB::transaction(function () use ($ticketId, $newStatus, $actor) {
            $ticket = DB::table('hd_tickets')->where('id', $ticketId)->lockForUpdate()->first();

            if ($ticket === null) {
                throw new DomainException('Tiket tidak ditemukan.');
            }

            $now = new DateTimeImmutable;

            DB::table('hd_tickets')->where('id', $ticketId)->update([
                'status' => $newStatus,
                'updated_at' => $now,
                'version' => $ticket->version + 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'hd_ticket',
                auditableId: $ticketId,
                action: AuditAction::Updated,
                oldValues: ['status' => $ticket->status],
                newValues: ['status' => $newStatus],
            ));
        });
    }
}
