<?php

declare(strict_types=1);

namespace App\Modules\Leave\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Bekal Cuti — isi jumlah + cairkan SEKALIGUS (satu langkah, beda dari
 * SPPD yang dua tahap approve-lalu-cair — di sini tidak ada "jumlah
 * yang disetujui" terpisah untuk disetujui duluan, karena memang tidak
 * ada rumus resmi: siapa yang mencairkan JUGA yang menentukan
 * jumlahnya). Guard swa-cair TIDAK relevan di sini (tidak ada
 * "approver" pada baris pencairan — beda dari SPPD/Lembur yang
 * mencegah penyetuju mencairkan buatannya sendiri).
 */
final class DisburseBekalCuti
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $id, int $amountCents, string $reference, AuditActor $actor): void
    {
        if ($amountCents <= 0) {
            throw new DomainException('Jumlah Bekal Cuti harus lebih dari nol.');
        }

        DB::transaction(function () use ($id, $amountCents, $reference, $actor) {
            $row = DB::table('pay_bekal_cuti_disbursements')->where('id', $id)->lockForUpdate()->first();

            if ($row === null) {
                throw new DomainException('Baris Bekal Cuti tidak ditemukan.');
            }

            if ($row->status !== 'pending') {
                throw new DomainException('Bekal Cuti ini sudah dicairkan sebelumnya.');
            }

            $now = new DateTimeImmutable;

            DB::table('pay_bekal_cuti_disbursements')->where('id', $id)->update([
                'amount_cents' => $amountCents,
                'status' => 'disbursed',
                'disbursed_by' => $actor->actorId,
                'disbursed_at' => $now,
                'disbursement_reference' => $reference,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'bekal_cuti_disbursement',
                auditableId: $id,
                action: AuditAction::Disbursed,
                newValues: ['amount_cents' => $amountCents],
                contextRef: $reference,
            ));
        });
    }
}
