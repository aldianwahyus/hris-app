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
 * Competency-Based Learning (BRD §5.1) — katalog kompetensi + peta
 * kompetensi per jabatan (required_level) + penanda kursus yang
 * menutup kompetensi apa (dasar RecommendCoursesForGap). HC
 * (permission:lms-catalog.manage yang SUDAH ADA).
 */
final class CompetencyController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $competencies = DB::table('lms_competencies')->orderBy('name')->get();

        return view('admin.lms-competencies', compact('competencies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $codeTaken = DB::table('lms_competencies')->where('code', $validated['code'])->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode kompetensi itu sudah dipakai.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_competencies')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_competency',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.competencies.index')->with('sukses', 'Kompetensi tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $competency = DB::table('lms_competencies')->where('id', $id)->first();
        abort_if($competency === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('lms_competencies')->where('id', $id)->update([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $competency->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_competency',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $competency,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.competencies.index')->with('sukses', 'Kompetensi diperbarui.');
    }

    public function mapPosition(string $positionId): View
    {
        $position = DB::table('md_positions')->where('id', $positionId)->first();
        abort_if($position === null, 404);

        $competencies = DB::table('lms_competencies')->where('is_active', true)->orderBy('name')->get();

        $requiredLevels = DB::table('lms_position_competencies')
            ->where('position_id', $positionId)
            ->pluck('required_level', 'competency_id');

        return view('admin.lms-position-competency-map', compact('position', 'competencies', 'requiredLevels'));
    }

    public function storePositionMapping(Request $request, string $positionId): RedirectResponse
    {
        $position = DB::table('md_positions')->where('id', $positionId)->first();
        abort_if($position === null, 404);

        $validated = $request->validate([
            'required_level' => ['nullable', 'array'],
            'required_level.*' => ['nullable', 'integer', 'min:0', 'max:5'],
        ]);

        $levels = $validated['required_level'] ?? [];
        $now = new DateTimeImmutable;

        DB::transaction(function () use ($positionId, $levels, $now) {
            DB::table('lms_position_competencies')->where('position_id', $positionId)->delete();

            foreach ($levels as $competencyId => $level) {
                if ((int) $level < 1) {
                    continue; // 0 atau kosong = tidak wajib untuk jabatan ini
                }

                DB::table('lms_position_competencies')->insert([
                    'id' => (string) Uuid7::generate(),
                    'position_id' => $positionId,
                    'competency_id' => $competencyId,
                    'required_level' => (int) $level,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);
            }
        });

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_position_competency_map',
            auditableId: $positionId,
            action: AuditAction::Updated,
            newValues: $levels,
        ));

        return redirect()->route('lms.admin.competencies.map-position', $positionId)->with('sukses', 'Peta kompetensi jabatan disimpan.');
    }

    public function mapCourse(string $courseId): View
    {
        $course = DB::table('lms_courses')->where('id', $courseId)->whereNull('deleted_at')->first();
        abort_if($course === null, 404);

        $competencies = DB::table('lms_competencies')->where('is_active', true)->orderBy('name')->get();

        $mapped = DB::table('lms_course_competencies')
            ->where('course_id', $courseId)
            ->pluck('competency_id')
            ->all();

        return view('admin.lms-course-competency-map', compact('course', 'competencies', 'mapped'));
    }

    public function storeCourseMapping(Request $request, string $courseId): RedirectResponse
    {
        $course = DB::table('lms_courses')->where('id', $courseId)->whereNull('deleted_at')->first();
        abort_if($course === null, 404);

        $validated = $request->validate([
            'competency_ids' => ['nullable', 'array'],
            'competency_ids.*' => ['uuid', 'exists:lms_competencies,id'],
        ]);

        $competencyIds = $validated['competency_ids'] ?? [];
        $now = new DateTimeImmutable;

        DB::transaction(function () use ($courseId, $competencyIds, $now) {
            DB::table('lms_course_competencies')->where('course_id', $courseId)->delete();

            foreach ($competencyIds as $competencyId) {
                DB::table('lms_course_competencies')->insert([
                    'id' => (string) Uuid7::generate(),
                    'course_id' => $courseId,
                    'competency_id' => $competencyId,
                    'created_at' => $now,
                ]);
            }
        });

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_course_competency_map',
            auditableId: $courseId,
            action: AuditAction::Updated,
            newValues: ['competency_ids' => $competencyIds],
        ));

        return redirect()->route('lms.admin.competencies.map-course', $courseId)->with('sukses', 'Kompetensi kursus disimpan.');
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
