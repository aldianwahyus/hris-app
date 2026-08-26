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
 * Learning Path & Career Development (BRD §5.2) — jalur pembelajaran
 * terstruktur per jabatan, HC (permission:lms-catalog.manage).
 */
final class LearningPathController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $paths = DB::table('lms_learning_paths as lp')
            ->join('md_positions as p', 'p.id', '=', 'lp.position_id')
            ->select('lp.*', 'p.name as position_name')
            ->orderBy('p.name')
            ->get();

        $courseCounts = DB::table('lms_learning_path_courses')
            ->select('learning_path_id', DB::raw('count(*) as jumlah'))
            ->groupBy('learning_path_id')
            ->pluck('jumlah', 'learning_path_id');

        $positions = DB::table('md_positions')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.lms-learning-paths', compact('paths', 'courseCounts', 'positions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'position_id' => ['required', 'uuid', 'exists:md_positions,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_learning_paths')->insert([
            'id' => $id,
            'position_id' => $validated['position_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_learning_path',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.learning-paths.index')->with('sukses', 'Learning path tersimpan.');
    }

    public function show(string $id): View
    {
        $path = DB::table('lms_learning_paths as lp')
            ->join('md_positions as p', 'p.id', '=', 'lp.position_id')
            ->where('lp.id', $id)
            ->select('lp.*', 'p.name as position_name')
            ->first();

        abort_if($path === null, 404);

        $pathCourses = DB::table('lms_learning_path_courses as lpc')
            ->join('lms_courses as c', 'c.id', '=', 'lpc.course_id')
            ->where('lpc.learning_path_id', $id)
            ->select('lpc.id', 'lpc.sequence', 'lpc.is_mandatory', 'c.title as course_title')
            ->orderBy('lpc.sequence')
            ->get();

        $availableCourses = DB::table('lms_courses')->whereNull('deleted_at')->where('is_active', true)->orderBy('title')->get(['id', 'title']);

        return view('admin.lms-learning-path-detail', compact('path', 'pathCourses', 'availableCourses'));
    }

    public function storeCourse(Request $request, string $pathId): RedirectResponse
    {
        $path = DB::table('lms_learning_paths')->where('id', $pathId)->first();
        abort_if($path === null, 404);

        $validated = $request->validate([
            'course_id' => ['required', 'uuid', 'exists:lms_courses,id'],
            'sequence' => ['required', 'integer', 'min:1'],
            'is_mandatory' => ['nullable', 'boolean'],
        ]);

        $sequenceTaken = DB::table('lms_learning_path_courses')
            ->where('learning_path_id', $pathId)
            ->where('sequence', $validated['sequence'])
            ->exists();

        if ($sequenceTaken) {
            return back()->withInput()->with('gagal', 'Urutan itu sudah dipakai di learning path ini.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_learning_path_courses')->insert([
            'id' => $id,
            'learning_path_id' => $pathId,
            'course_id' => $validated['course_id'],
            'sequence' => $validated['sequence'],
            'is_mandatory' => $validated['is_mandatory'] ?? true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_learning_path_course',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.learning-paths.show', $pathId)->with('sukses', 'Kursus ditambahkan ke learning path.');
    }

    public function destroyCourse(Request $request, string $pathId, string $id): RedirectResponse
    {
        $row = DB::table('lms_learning_path_courses')->where('id', $id)->where('learning_path_id', $pathId)->first();
        abort_if($row === null, 404);

        DB::table('lms_learning_path_courses')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_learning_path_course',
            auditableId: $id,
            action: AuditAction::Deleted,
        ));

        return redirect()->route('lms.admin.learning-paths.show', $pathId)->with('sukses', 'Kursus dihapus dari learning path.');
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
