<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Employee\Domain\ProfileChangeConflict;
use App\Modules\Employee\Domain\ProfileChangeValidator;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * hr_approver (checker) menyetujui atau menolak satu pengajuan
 * perubahan data induk pegawai. Persetujuan-lah yang MENERAPKAN
 * proposed_changes ke emp_employees — sebelum itu, emp_employees
 * tidak pernah tersentuh oleh pengajuan yang masih pending.
 */
final class DecideEmployeeProfileChange
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function approve(string $requestId, AuditActor $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($requestId, $actor, $note) {
            $request = DB::table('emp_profile_change_requests')
                ->where('id', $requestId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new RuntimeException('Pengajuan tidak ditemukan atau sudah diputus sebelumnya.');
            }

            $proposedChanges = json_decode($request->proposed_changes, true);

            /** @var object{office_id: string, position_id: string, person_grade: ?int, job_grade: ?int, version: int}|null $employee */
            $employee = DB::table('emp_employees')->where('id', $request->employee_id)->first();

            if ($employee === null) {
                throw new RuntimeException("Pegawai (id: {$request->employee_id}) tidak ditemukan.");
            }

            // Divalidasi ULANG terhadap data TERKINI (bukan snapshot saat
            // diajukan) — pengajuan lain bisa saja sudah lebih dulu
            // disetujui dan mengubah field yang sama atau field terkait
            // (mis. permanent_date) sejak baris ini diajukan.
            $currentValues = [];
            foreach (ProfileChangeValidator::fieldsToSnapshot($proposedChanges) as $field) {
                $currentValues[$field] = $employee->{$field} ?? null;
            }
            ProfileChangeValidator::validate($proposedChanges, $currentValues);

            $now = new DateTimeImmutable;

            $affected = DB::table('emp_employees')
                ->where('id', $request->employee_id)
                ->where('version', $employee->version)
                ->update(array_merge($proposedChanges, [
                    'updated_at' => $now,
                    'version' => $employee->version + 1,
                ]));

            if ($affected === 0) {
                throw ProfileChangeConflict::forEmployee($request->employee_id);
            }

            $this->recordPositionHistoryIfChanged($request->employee_id, $employee, $proposedChanges, $requestId, $now);

            DB::table('emp_profile_change_requests')->where('id', $requestId)->update([
                'status' => 'approved',
                'decided_by' => $actor->actorId,
                'decided_at' => $now,
                'decision_note' => $note,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'employee_profile_change',
                auditableId: $request->employee_id,
                action: AuditAction::Approved,
                oldValues: $currentValues,
                newValues: $proposedChanges,
                contextRef: $requestId,
            ));
        });
    }

    /**
     * Merekam SNAPSHOT lengkap posisi (bukan hanya field yang berubah)
     * ke emp_position_history — dasar laporan "Record Pegawai" —
     * HANYA bila salah satu dari office_id/position_id/person_grade/
     * job_grade benar-benar termasuk perubahan yang baru disetujui.
     * $employee di sini adalah nilai SEBELUM UPDATE (di-fetch di awal
     * approve()) — digabung dengan $proposedChanges supaya baris
     * riwayat berisi keadaan LENGKAP SETELAH perubahan, bukan cuma
     * delta-nya (laporan per-bulan butuh baris utuh untuk diambil
     * langsung, tanpa perlu menggabungkan beberapa baris riwayat).
     *
     * @param  object{office_id: string, position_id: string, person_grade: ?int, job_grade: ?int}  $employee
     * @param  array<string, mixed>  $proposedChanges
     */
    private function recordPositionHistoryIfChanged(string $employeeId, object $employee, array $proposedChanges, string $requestId, DateTimeImmutable $now): void
    {
        $positionFields = ['office_id', 'position_id', 'person_grade', 'job_grade'];

        if (array_intersect(array_keys($proposedChanges), $positionFields) === []) {
            return;
        }

        $decisionLetterId = DB::table('emp_decision_letters')
            ->where('profile_change_request_id', $requestId)
            ->value('id');

        DB::table('emp_position_history')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'office_id' => $proposedChanges['office_id'] ?? $employee->office_id,
            'position_id' => $proposedChanges['position_id'] ?? $employee->position_id,
            'person_grade' => $proposedChanges['person_grade'] ?? $employee->person_grade,
            'job_grade' => $proposedChanges['job_grade'] ?? $employee->job_grade,
            'effective_from' => $now->format('Y-m-d'),
            'decision_letter_id' => $decisionLetterId,
            'created_at' => $now,
            'version' => 1,
        ]);
    }

    public function reject(string $requestId, AuditActor $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($requestId, $actor, $note) {
            $request = DB::table('emp_profile_change_requests')
                ->where('id', $requestId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new RuntimeException('Pengajuan tidak ditemukan atau sudah diputus sebelumnya.');
            }

            $now = new DateTimeImmutable;

            DB::table('emp_profile_change_requests')->where('id', $requestId)->update([
                'status' => 'rejected',
                'decided_by' => $actor->actorId,
                'decided_at' => $now,
                'decision_note' => $note,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'employee_profile_change',
                auditableId: $request->employee_id,
                action: AuditAction::Rejected,
                contextRef: $requestId,
            ));
        });
    }
}
