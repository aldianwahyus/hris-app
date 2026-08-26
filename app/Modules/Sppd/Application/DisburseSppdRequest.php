<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Bendahara mencairkan SPPD yang SUDAH disetujui (status 'approved' ->
 * 'disbursed') — pencair TIDAK BOLEH menjadi orang yang sama yang
 * menyetujui pengajuan tersebut (§6.3), ditegakkan di sini, bukan
 * hanya di lapisan Interfaces. Mengikuti pola
 * Payroll\Application\DecidePayrollRun (kunci baris, jaga status,
 * jaga aktor, catat audit).
 *
 * HRIS hanya MENCATAT bahwa pencairan terjadi (nomor referensi dari
 * Core Banking/Treasury eksternal) — transfer dana sesungguhnya
 * dieksekusi di luar sistem ini.
 */
final class DisburseSppdRequest
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $requestId, string $reference, AuditActor $actor): void
    {
        DB::transaction(function () use ($requestId, $reference, $actor) {
            $spd = DB::table('spd_requests')->where('id', $requestId)->lockForUpdate()->first();

            if ($spd === null) {
                throw new DomainException('Pengajuan SPPD tidak ditemukan.');
            }

            if ($spd->status !== 'approved') {
                throw new DomainException('Hanya SPPD berstatus disetujui yang dapat dicairkan.');
            }

            if ($spd->approver_id === $actor->actorId) {
                throw new DomainException('Tidak dapat mencairkan SPPD yang Anda setujui sendiri.');
            }

            $now = new DateTimeImmutable;

            DB::table('spd_requests')->where('id', $requestId)->update([
                'status' => 'disbursed',
                'disbursed_by' => $actor->actorId,
                'disbursed_at' => $now,
                'disbursement_reference' => $reference,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'spd_request',
                auditableId: $requestId,
                action: AuditAction::Disbursed,
                contextRef: $reference,
            ));
        });
    }
}
