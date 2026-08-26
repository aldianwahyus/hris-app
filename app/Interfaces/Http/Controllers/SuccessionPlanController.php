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
 * Succession planning (BRD §5.6) — HC menandai posisi kunci + kandidat
 * penerus beserta tingkat kesiapannya. permission:lms-catalog.manage.
 */
final class SuccessionPlanController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $plans = DB::table('lms_succession_plans as sp')
            ->join('md_positions as p', 'p.id', '=', 'sp.position_id')
            ->join('emp_employees as e', 'e.id', '=', 'sp.candidate_employee_id')
            ->where('sp.is_active', true)
            ->select('sp.*', 'p.name as position_name', 'e.full_name as candidate_name', 'e.nrp as candidate_nrp')
            ->orderBy('p.name')
            ->get()
            ->groupBy('position_id');

        $positions = DB::table('md_positions')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $employees = DB::table('emp_employees')->orderBy('full_name')->get(['id', 'full_name', 'nrp']);

        return view('admin.lms-succession-plans', compact('plans', 'positions', 'employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'position_id' => ['required', 'uuid', 'exists:md_positions,id'],
            'candidate_employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'readiness_level' => ['required', 'string', 'in:ready_now,ready_1_2_years,ready_3_5_years'],
            'notes' => ['nullable', 'string'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_succession_plans')->insert([
            'id' => $id,
            'position_id' => $validated['position_id'],
            'candidate_employee_id' => $validated['candidate_employee_id'],
            'readiness_level' => $validated['readiness_level'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => $this->actor->employeeId(),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_succession_plan',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.succession.index')->with('sukses', 'Kandidat suksesi ditambahkan.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $plan = DB::table('lms_succession_plans')->where('id', $id)->first();
        abort_if($plan === null, 404);

        DB::table('lms_succession_plans')->where('id', $id)->update(['is_active' => false, 'updated_at' => new DateTimeImmutable]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_succession_plan',
            auditableId: $id,
            action: AuditAction::Deleted,
        ));

        return redirect()->route('lms.admin.succession.index')->with('sukses', 'Kandidat suksesi dihapus dari daftar.');
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
