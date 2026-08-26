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
 * Organisasi yang Pernah Diikuti — pegawai input SENDIRI lewat CV
 * Saya, tulis LANGSUNG tanpa persetujuan.
 */
final class EmployeeOrganizationController extends Controller
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
            'organization_name' => ['required', 'string', 'max:200'],
            'role' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('emp_organizations')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'organization_name' => $validated['organization_name'],
            'role' => $validated['role'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request, $employeeId),
            auditableType: 'employee_organization',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('ess.cv')->with('sukses', 'Organisasi tersimpan.');
    }

    public function destroy(string $id, Request $request): RedirectResponse
    {
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $row = DB::table('emp_organizations')->where('id', $id)->where('employee_id', $employeeId)->first();

        abort_if($row === null, 404);

        DB::table('emp_organizations')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request, $employeeId),
            auditableType: 'employee_organization',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['organization_name' => $row->organization_name],
        ));

        return redirect()->route('ess.cv')->with('sukses', 'Organisasi dihapus.');
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
