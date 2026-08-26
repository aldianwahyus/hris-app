<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pelatihan yang Pernah Diikuti — pegawai input SENDIRI lewat CV Saya,
 * tulis LANGSUNG tanpa persetujuan (pola sama UpdateOwnPersonalDetails).
 * TANPA update() — salah isi, hapus lalu tambah ulang.
 */
final class EmployeeTrainingController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'training_name' => ['required', 'string', 'max:200'],
            'organizer' => ['nullable', 'string', 'max:150'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('emp_trainings')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'training_name' => $validated['training_name'],
            'organizer' => $validated['organizer'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request, $employeeId),
            auditableType: 'employee_training',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('ess.cv')->with('sukses', 'Pelatihan tersimpan.');
    }

    public function destroy(string $id, Request $request): RedirectResponse
    {
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $row = DB::table('emp_trainings')->where('id', $id)->where('employee_id', $employeeId)->first();

        abort_if($row === null, 404);

        DB::table('emp_trainings')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request, $employeeId),
            auditableType: 'employee_training',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['training_name' => $row->training_name],
        ));

        return redirect()->route('ess.cv')->with('sukses', 'Pelatihan dihapus.');
    }

    private function currentAuditActor(Request $request, string $employeeId): AuditActor
    {
        return new AuditActor(
            actorId: $employeeId,
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
