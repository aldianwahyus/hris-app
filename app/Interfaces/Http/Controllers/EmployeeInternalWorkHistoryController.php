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
 * Riwayat Kerja di Bank NTB Syariah — HR-only, tulis LANGSUNG tanpa
 * maker-checker. position_description SENGAJA teks bebas, TIDAK
 * terikat md_positions — ini catatan historis, bukan penugasan aktif.
 */
final class EmployeeInternalWorkHistoryController extends Controller
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
            'position_description' => ['required', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('emp_internal_work_histories')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'position_description' => $validated['position_description'],
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
            auditableType: 'employee_internal_work_history',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return back()->with('sukses', 'Riwayat kerja internal tersimpan.');
    }

    public function destroy(string $employeeId, string $id, Request $request): RedirectResponse
    {
        $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());

        $row = DB::table('emp_internal_work_histories')->where('id', $id)->where('employee_id', $employeeId)->first();

        abort_if($row === null, 404);

        DB::table('emp_internal_work_histories')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'employee_internal_work_history',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['position_description' => $row->position_description],
        ));

        return back()->with('sukses', 'Riwayat kerja internal dihapus.');
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
