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
use Illuminate\View\View;

/**
 * Penugasan shift per pegawai — SYSADMIN/Admin HC, bank-wide. Saat
 * penugasan baru dibuat, penugasan lama yang masih terbuka (effective_to
 * NULL) otomatis ditutup sehari sebelum penugasan baru mulai berlaku —
 * satu pegawai hanya boleh punya SATU penugasan aktif pada tanggal
 * mana pun.
 */
final class ShiftAssignmentController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $today = now()->format('Y-m-d');

        $assignments = DB::table('shf_employee_assignments as a')
            ->join('emp_employees as e', 'e.id', '=', 'a.employee_id')
            ->join('shf_shift_patterns as p', 'p.id', '=', 'a.shift_pattern_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->select(
                'a.id', 'a.effective_from', 'a.effective_to',
                'e.full_name', 'e.nrp', 'o.name as office_name', 'p.name as pattern_name',
            )
            ->where(fn ($q) => $q->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today))
            ->orderBy('e.full_name')
            ->get();

        $employees = DB::table('emp_employees')->orderBy('full_name')->get(['id', 'full_name', 'nrp']);
        $patterns = DB::table('shf_shift_patterns')->where('is_active', true)->orderBy('start_time')->get(['id', 'code', 'name']);

        return view('admin.shift-assignments', compact('assignments', 'employees', 'patterns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'shift_pattern_id' => ['required', 'uuid', 'exists:shf_shift_patterns,id'],
            'effective_from' => ['required', 'date'],
        ]);

        $now = new DateTimeImmutable;

        DB::transaction(function () use ($validated, $now) {
            DB::table('shf_employee_assignments')
                ->where('employee_id', $validated['employee_id'])
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => (new DateTimeImmutable($validated['effective_from']))->modify('-1 day')->format('Y-m-d'),
                    'updated_at' => $now,
                ]);

            $id = (string) Uuid7::generate();

            DB::table('shf_employee_assignments')->insert([
                'id' => $id,
                'employee_id' => $validated['employee_id'],
                'shift_pattern_id' => $validated['shift_pattern_id'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => null,
                'created_by' => $this->actor->employeeId(),
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: new AuditActor(
                    actorId: $this->actor->employeeId(),
                    actorRole: implode(',', $this->actor->roles()),
                ),
                auditableType: 'shift_assignment',
                auditableId: $id,
                action: AuditAction::Created,
                newValues: $validated,
            ));
        });

        return redirect()->route('sysadmin.shift-assignments.index')->with('sukses', 'Penugasan shift tersimpan.');
    }
}
