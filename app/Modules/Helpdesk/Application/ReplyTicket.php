<?php

declare(strict_types=1);

namespace App\Modules\Helpdesk\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Membalas satu tiket Helpdesk (append-only, kedua arah). Balasan
 * dari PEGAWAI PENGAJU pada tiket berstatus 'selesai' otomatis
 * membuka lagi statusnya jadi 'diproses' — pegawai tidak perlu
 * meminta HC membuka ulang tiket hanya untuk menambahkan info.
 * Balasan HC (author BUKAN pegawai pengaju) tidak mengubah status
 * secara implisit — HC mengubahnya sendiri lewat UpdateTicketStatus.
 */
final class ReplyTicket
{
    public function handle(
        string $ticketId,
        string $authorEmployeeId,
        string $message,
        bool $isInternalNote,
    ): string {
        return DB::transaction(function () use ($ticketId, $authorEmployeeId, $message, $isInternalNote) {
            $ticket = DB::table('hd_tickets')->where('id', $ticketId)->lockForUpdate()->first();

            if ($ticket === null) {
                throw new DomainException('Tiket tidak ditemukan.');
            }

            if (in_array($ticket->status, ['ditutup'], true)) {
                throw new DomainException('Tiket ini sudah ditutup dan tidak dapat dibalas lagi.');
            }

            $now = new DateTimeImmutable;
            $id = (string) Uuid7::generate();

            DB::table('hd_ticket_replies')->insert([
                'id' => $id,
                'ticket_id' => $ticketId,
                'author_employee_id' => $authorEmployeeId,
                'message' => $message,
                'is_internal_note' => $isInternalNote,
                'created_at' => $now,
            ]);

            $isReplyFromRequester = $authorEmployeeId === $ticket->employee_id;

            if ($isReplyFromRequester && $ticket->status === 'selesai') {
                DB::table('hd_tickets')->where('id', $ticketId)->update([
                    'status' => 'diproses',
                    'updated_at' => $now,
                    'version' => $ticket->version + 1,
                ]);
            }

            return $id;
        });
    }
}
