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

/**
 * Riwayat Kerja di Luar Bank NTB Syariah — HR-only, tulis LANGSUNG
 * tanpa maker-checker.
 */
final class EmployeeExternalWorkHistoryController extends Controller
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
            'company_name' => ['required', 'string', 'max:150'],
            'position' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('emp_external_work_histories')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'company_name' => $validated['company_name'],
            'position' => $validated['position'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'employee_external_work_history',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return back()->with('sukses', 'Riwayat kerja eksternal tersimpan.');
    }

    public function destroy(string $employeeId, string $id, Request $request): RedirectResponse
    {
        $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());

        $row = DB::table('emp_external_work_histories')->where('id', $id)->where('employee_id', $employeeId)->first();

        abort_if($row === null, 404);

        DB::table('emp_external_work_histories')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'employee_external_work_history',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['company_name' => $row->company_name, 'position' => $row->position],
        ));

        return back()->with('sukses', 'Riwayat kerja eksternal dihapus.');
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
