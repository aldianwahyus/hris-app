<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SYSADMIN (maker) mengusulkan PEGAWAI BARU — pola PERSIS
 * SubmitEmployeeProfileChange (§6.3 tetap ditegakkan untuk penambahan,
 * bukan cuma revisi): TIDAK langsung menulis emp_employees, hanya
 * mencatat usulan yang menunggu hr_approver (checker, lihat
 * DecideNewEmployeeRequest).
 */
final class SubmitNewEmployeeRequest
{
    public function __construct(private readonly AuditRepository $audit) {}

    /** @param  array<string, mixed>  $proposedData */
    public function handle(array $proposedData, string $requestedBy, AuditActor $actor): string
    {
        $nrp = $proposedData['nrp'] ?? null;

        if (! is_string($nrp) || trim($nrp) === '') {
            throw new InvalidArgumentException('NRP wajib diisi.');
        }

        $nrpExists = DB::table('emp_employees')->where('nrp', $nrp)->exists()
            // Dicek di PHP, bukan query JSON di DB — jumlah baris pending
            // selalu kecil, dan ini menghindari sintaks JSON yang berbeda
            // antar vendor basis data.
            || DB::table('emp_new_employee_requests')
                ->where('status', 'pending')
                ->get(['proposed_data'])
                ->contains(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === $nrp);

        if ($nrpExists) {
            throw new InvalidArgumentException("NRP \"{$nrp}\" sudah dipakai pegawai lain atau sedang diusulkan.");
        }

        return DB::transaction(function () use ($proposedData, $requestedBy, $actor) {
            $now = new DateTimeImmutable;
            $requestId = (string) Uuid7::generate();

            DB::table('emp_new_employee_requests')->insert([
                'id' => $requestId,
                'requested_by' => $requestedBy,
                'proposed_data' => json_encode($proposedData),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'new_employee_request',
                auditableId: $requestId,
                action: AuditAction::Submitted,
                newValues: $proposedData,
                contextRef: $proposedData['nrp'],
            ));

            return $requestId;
        });
    }
}
