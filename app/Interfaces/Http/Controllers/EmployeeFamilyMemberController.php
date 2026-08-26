<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\ResolveEmployeeForHrAction;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Data Pasangan & Anak — HR-only (lingkup ditegakkan
 * ResolveEmployeeForHrAction), tulis LANGSUNG tanpa maker-checker
 * (administratif/historis, bukan keputusan bisnis harian).
 */
final class EmployeeFamilyMemberController extends Controller
{
    public function __construct(
        private readonly ResolveEmployeeForHrAction $resolveEmployee,
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function store(string $employeeId, Request $request): RedirectResponse
    {
        $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());

        $validated = $request->validate([
            'relationship_type' => ['required', 'string', Rule::in(['pasangan', 'anak'])],
            'full_name' => ['required', 'string', 'max:150'],
            'birth_date' => ['nullable', 'date'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('emp_family_members')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'relationship_type' => $validated['relationship_type'],
            'full_name' => $validated['full_name'],
            'birth_date' => $validated['birth_date'] ?? null,
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'employee_family_member',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return back()->with('sukses', 'Data keluarga tersimpan.');
    }

    public function destroy(string $employeeId, string $id, Request $request): RedirectResponse
    {
        $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());

        $row = DB::table('emp_family_members')->where('id', $id)->where('employee_id', $employeeId)->first();

        abort_if($row === null, 404);

        DB::table('emp_family_members')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'employee_family_member',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['full_name' => $row->full_name, 'relationship_type' => $row->relationship_type],
        ));

        return back()->with('sukses', 'Data keluarga dihapus.');
    }

    private function currentAuditActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
