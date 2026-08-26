<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Lms\Application\RecommendCoursesForGap;
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
 * Skill mapping individu (BRD §5.1) — HC menilai current_level per
 * kompetensi seorang pegawai, dibandingkan required_level jabatannya
 * (GAP), plus rekomendasi kursus otomatis (RecommendCoursesForGap)
 * yang menutup gap itu.
 */
final class EmployeeCompetencyController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
        private readonly RecommendCoursesForGap $recommend,
    ) {}

    public function show(string $employeeId): View
    {
        $employee = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->where('e.id', $employeeId)
            ->select('e.id', 'e.full_name', 'e.nrp', 'e.position_id', 'p.name as position_name')
            ->first();

        abort_if($employee === null, 404);

        $required = DB::table('lms_position_competencies as pc')
            ->join('lms_competencies as c', 'c.id', '=', 'pc.competency_id')
            ->where('pc.position_id', $employee->position_id)
            ->select('c.id', 'c.name', 'c.category', 'pc.required_level')
            ->orderBy('c.name')
            ->get();

        $current = DB::table('lms_employee_competencies')
            ->where('employee_id', $employeeId)
            ->pluck('current_level', 'competency_id');

        $rows = $required->map(function ($c) use ($current) {
            $c->current_level = $current[$c->id] ?? 0;
            $c->gap = max(0, $c->required_level - $c->current_level);

            return $c;
        });

        $recommendations = $this->recommend->forEmployee($employeeId);

        return view('admin.lms-employee-competency', compact('employee', 'rows', 'recommendations'));
    }

    public function update(Request $request, string $employeeId): RedirectResponse
    {
        $employee = DB::table('emp_employees')->where('id', $employeeId)->first();
        abort_if($employee === null, 404);

        $validated = $request->validate([
            'current_level' => ['required', 'array'],
            'current_level.*' => ['required', 'integer', 'min:0', 'max:5'],
        ]);

        $now = new DateTimeImmutable;
        $assessedBy = $this->actor->employeeId();

        DB::transaction(function () use ($employeeId, $validated, $now, $assessedBy) {
            foreach ($validated['current_level'] as $competencyId => $level) {
                $existing = DB::table('lms_employee_competencies')
                    ->where('employee_id', $employeeId)
                    ->where('competency_id', $competencyId)
                    ->first();

                if ($existing === null) {
                    DB::table('lms_employee_competencies')->insert([
                        'id' => (string) Uuid7::generate(),
                        'employee_id' => $employeeId,
                        'competency_id' => $competencyId,
                        'current_level' => (int) $level,
                        'assessed_by' => $assessedBy,
                        'assessed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'version' => 1,
                    ]);
                } else {
                    DB::table('lms_employee_competencies')->where('id', $existing->id)->update([
                        'current_level' => (int) $level,
                        'assessed_by' => $assessedBy,
                        'assessed_at' => $now,
                        'updated_at' => $now,
                        'version' => $existing->version + 1,
                    ]);
                }
            }
        });

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_employee_competency',
            auditableId: $employeeId,
            action: AuditAction::Updated,
            newValues: $validated['current_level'],
        ));

        return redirect()->route('lms.admin.employee-competency.show', $employeeId)->with('sukses', 'Level kompetensi tersimpan.');
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
