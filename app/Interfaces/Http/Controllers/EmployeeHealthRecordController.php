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
 * Riwayat Kesehatan — HR-only, tulis LANGSUNG tanpa maker-checker. Log
 * berulang (tanggal + catatan bebas), BUKAN satu baris tetap per
 * pegawai — konsisten dengan 4 jenis riwayat HR-only lainnya.
 */
final class EmployeeHealthRecordController extends Controller
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
            'record_date' => ['required', 'date'],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('emp_health_records')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'record_date' => $validated['record_date'],
            'note' => $validated['note'],
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'employee_health_record',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return back()->with('sukses', 'Riwayat kesehatan tersimpan.');
    }

    public function destroy(string $employeeId, string $id, Request $request): RedirectResponse
    {
        $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());

        $row = DB::table('emp_health_records')->where('id', $id)->where('employee_id', $employeeId)->first();

        abort_if($row === null, 404);

        DB::table('emp_health_records')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'employee_health_record',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['record_date' => $row->record_date],
        ));

        return back()->with('sukses', 'Riwayat kesehatan dihapus.');
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
