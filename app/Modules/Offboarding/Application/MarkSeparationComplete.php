<?php

declare(strict_types=1);

namespace App\Modules\Offboarding\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Menuntaskan pemisahan — HANYA setelah SELURUH item clearance
 * selesai (ditegakkan di sini, bukan otomatis saat item terakhir
 * dicentang — HC yang menuntaskan secara sadar, pola berbeda dari
 * Onboarding yang otomatis, karena tindakan ini mengunci login
 * pegawai). Mengisi `emp_employees.separated_at` — dicek
 * AuthenticateEmployee untuk menolak login berikutnya.
 */
final class MarkSeparationComplete
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $separationId, AuditActor $actor): void
    {
        DB::transaction(function () use ($separationId, $actor) {
            $separation = DB::table('off_separation_requests')->where('id', $separationId)->lockForUpdate()->first();

            if ($separation === null) {
                throw new DomainException('Pengajuan pemisahan tidak ditemukan.');
            }

            if ($separation->status !== 'approved') {
                throw new DomainException('Pemisahan hanya dapat dituntaskan setelah disetujui.');
            }

            $unfinishedCount = DB::table('off_clearance_items')
                ->where('separation_id', $separationId)
                ->where('is_done', false)
                ->count();

            if ($unfinishedCount > 0) {
                throw new DomainException("Masih ada {$unfinishedCount} item clearance yang belum selesai.");
            }

            $now = new DateTimeImmutable;

            DB::table('off_separation_requests')->where('id', $separationId)->update([
                'status' => 'selesai',
                'updated_at' => $now,
                'version' => $separation->version + 1,
            ]);

            DB::table('emp_employees')->where('id', $separation->employee_id)->update([
                'separated_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'off_separation_request',
                auditableId: $separationId,
                action: AuditAction::Executed,
                newValues: ['employee_id' => $separation->employee_id, 'separated_at' => $now->format('Y-m-d H:i:s')],
                contextRef: $separationId,
            ));
        });
    }
}
