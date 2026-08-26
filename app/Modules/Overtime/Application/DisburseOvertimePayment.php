<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Tahap PEMBAYARAN Lembur — setelah 2 tahap persetujuan SUDAH ADA
 * (ApprovalQueueController: Atasan Langsung → Pimpinan Kantor). Pola
 * PERSIS DisburseSppdRequest (§6.3: penyetuju TIDAK BOLEH jadi
 * pencair yang sama). Siapa BOLEH mencairkan (Admin HC untuk kantor
 * pusat, Admin Cabang pemilik kantor untuk cabang/KCP) ditegakkan di
 * Interfaces (OvertimeDisbursementController), BUKAN di sini — kelas
 * ini murni transisi status + guard swa-cair, sama seperti pola SPPD.
 */
final class DisburseOvertimePayment
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $requestId, string $reference, AuditActor $actor): void
    {
        DB::transaction(function () use ($requestId, $reference, $actor) {
            $request = DB::table('ovt_requests')->where('id', $requestId)->lockForUpdate()->first();

            if ($request === null) {
                throw new DomainException('Pengajuan lembur tidak ditemukan.');
            }

            if ($request->status !== 'approved') {
                throw new DomainException('Hanya pengajuan lembur berstatus approved yang dapat dibayarkan.');
            }

            if ($request->approver_id === $actor->actorId) {
                throw new DomainException('Tidak dapat membayarkan lembur yang Anda setujui sendiri.');
            }

            $now = new DateTimeImmutable;

            DB::table('ovt_requests')->where('id', $requestId)->update([
                'status' => 'disbursed',
                'disbursed_by' => $actor->actorId,
                'disbursed_at' => $now,
                'disbursement_reference' => $reference,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'ovt_request',
                auditableId: $requestId,
                action: AuditAction::Disbursed,
                contextRef: $request->spkl_number,
            ));
        });
    }
}
