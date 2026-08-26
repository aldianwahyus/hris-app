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
 * Penghargaan yang Pernah Diterima — pegawai input SENDIRI lewat CV
 * Saya, tulis LANGSUNG tanpa persetujuan.
 */
final class EmployeeAwardController extends Controller
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
            'award_name' => ['required', 'string', 'max:200'],
            'issuer' => ['nullable', 'string', 'max:150'],
            'award_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('emp_awards')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'award_name' => $validated['award_name'],
            'issuer' => $validated['issuer'] ?? null,
            'award_date' => $validated['award_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request, $employeeId),
            auditableType: 'employee_award',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('ess.cv')->with('sukses', 'Penghargaan tersimpan.');
    }

    public function destroy(string $id, Request $request): RedirectResponse
    {
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $row = DB::table('emp_awards')->where('id', $id)->where('employee_id', $employeeId)->first();

        abort_if($row === null, 404);

        DB::table('emp_awards')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request, $employeeId),
            auditableType: 'employee_award',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['award_name' => $row->award_name],
        ));

        return redirect()->route('ess.cv')->with('sukses', 'Penghargaan dihapus.');
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
