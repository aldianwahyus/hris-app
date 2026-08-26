<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Lms\Application\ComputeLearningPathProgress;
use App\Modules\Lms\Application\ComputeTalentReadiness;
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
 * Talent Management (BRD §5.6) — HC (permission:lms-catalog.manage).
 * performance_score/potential_score MANUAL (proksi, lihat docblock
 * migrasi); readiness_score DIHITUNG (ComputeTalentReadiness).
 */
final class TalentProfileController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
        private readonly ComputeTalentReadiness $readiness,
        private readonly ComputeLearningPathProgress $pathProgress,
        private readonly RecommendCoursesForGap $recommend,
    ) {}

    public function index(): View
    {
        $employees = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->leftJoin('lms_talent_profiles as tp', 'tp.employee_id', '=', 'e.id')
            ->select('e.id', 'e.full_name', 'e.nrp', 'p.name as position_name', 'tp.performance_score', 'tp.potential_score')
            ->orderBy('e.full_name')
            ->get();

        // Grid 9-box: baris = performance (rendah/sedang/tinggi), kolom
        // = potential (rendah/sedang/tinggi). Skor 1-2=rendah, 3=sedang,
        // 4-5=tinggi. Pegawai TANPA skor dikelompokkan terpisah (belum dinilai).
        $bucket = fn (?int $score) => match (true) {
            $score === null => null,
            $score <= 2 => 'rendah',
            $score === 3 => 'sedang',
            default => 'tinggi',
        };

        $grid = [];
        $unassessed = [];

        foreach ($employees as $e) {
            $perfBucket = $bucket($e->performance_score);
            $potBucket = $bucket($e->potential_score);

            if ($perfBucket === null || $potBucket === null) {
                $unassessed[] = $e;

                continue;
            }

            $grid[$perfBucket][$potBucket][] = $e;
        }

        return view('admin.lms-talent-9box', compact('grid', 'unassessed'));
    }

    public function show(string $employeeId): View
    {
        $employee = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->where('e.id', $employeeId)
            ->select('e.id', 'e.full_name', 'e.nrp', 'p.name as position_name')
            ->first();

        abort_if($employee === null, 404);

        $profile = DB::table('lms_talent_profiles')->where('employee_id', $employeeId)->first();
        $readinessScore = $this->readiness->forEmployee($employeeId);
        $pathProgress = $this->pathProgress->forEmployee($employeeId);
        $recommendations = $this->recommend->forEmployee($employeeId);

        return view('admin.lms-talent-profile', compact('employee', 'profile', 'readinessScore', 'pathProgress', 'recommendations'));
    }

    public function update(Request $request, string $employeeId): RedirectResponse
    {
        $employee = DB::table('emp_employees')->where('id', $employeeId)->first();
        abort_if($employee === null, 404);

        $validated = $request->validate([
            'performance_score' => ['required', 'integer', 'min:1', 'max:5'],
            'potential_score' => ['required', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
        ]);

        $existing = DB::table('lms_talent_profiles')->where('employee_id', $employeeId)->first();
        $now = new DateTimeImmutable;
        $assessedBy = $this->actor->employeeId();

        if ($existing === null) {
            DB::table('lms_talent_profiles')->insert([
                'id' => (string) Uuid7::generate(),
                'employee_id' => $employeeId,
                'performance_score' => $validated['performance_score'],
                'potential_score' => $validated['potential_score'],
                'notes' => $validated['notes'] ?? null,
                'assessed_by' => $assessedBy,
                'assessed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);
        } else {
            DB::table('lms_talent_profiles')->where('id', $existing->id)->update([
                'performance_score' => $validated['performance_score'],
                'potential_score' => $validated['potential_score'],
                'notes' => $validated['notes'] ?? null,
                'assessed_by' => $assessedBy,
                'assessed_at' => $now,
                'updated_at' => $now,
                'version' => $existing->version + 1,
            ]);
        }

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_talent_profile',
            auditableId: $employeeId,
            action: AuditAction::Updated,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.talent.show', $employeeId)->with('sukses', 'Profil talenta tersimpan.');
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
