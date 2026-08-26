<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Lms\Application\GradeAssessmentAttempt;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Assessment Center (BRD §5.4) — HC (permission:lms-catalog.manage):
 * CRUD assessment + bank soal (MILIK satu assessment, lihat docblock
 * migrasi), laporan hasil (attempts), dan penilaian esai manual (grade).
 */
final class AssessmentController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
        private readonly GradeAssessmentAttempt $gradeAttempt,
    ) {}

    public function index(): View
    {
        $assessments = DB::table('lms_assessments as a')
            ->leftJoin('lms_courses as c', 'c.id', '=', 'a.course_id')
            ->select('a.*', 'c.title as course_title')
            ->orderByDesc('a.created_at')
            ->get();

        $questionCounts = DB::table('lms_assessment_questions')
            ->select('assessment_id', DB::raw('count(*) as jumlah'))
            ->groupBy('assessment_id')
            ->pluck('jumlah', 'assessment_id');

        $attemptCounts = DB::table('lms_assessment_attempts')
            ->select('assessment_id', DB::raw('count(*) as jumlah'))
            ->groupBy('assessment_id')
            ->pluck('jumlah', 'assessment_id');

        $courses = DB::table('lms_courses')->whereNull('deleted_at')->orderBy('title')->get(['id', 'title']);

        return view('admin.lms-assessments', compact('assessments', 'questionCounts', 'attemptCounts', 'courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'course_id' => ['nullable', 'uuid', 'exists:lms_courses,id'],
            'passing_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_assessments')->insert([
            'id' => $id,
            'course_id' => $validated['course_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'passing_score' => $validated['passing_score'],
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'is_active' => true,
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_assessment',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.assessments.index')->with('sukses', 'Asesmen tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $assessment = DB::table('lms_assessments')->where('id', $id)->first();
        abort_if($assessment === null, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'passing_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('lms_assessments')->where('id', $id)->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'passing_score' => $validated['passing_score'],
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $assessment->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_assessment',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $assessment,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.assessments.index')->with('sukses', 'Asesmen diperbarui.');
    }

    public function questions(string $assessmentId): View
    {
        $assessment = DB::table('lms_assessments')->where('id', $assessmentId)->first();
        abort_if($assessment === null, 404);

        $questions = DB::table('lms_assessment_questions')
            ->where('assessment_id', $assessmentId)
            ->orderBy('sequence')
            ->get();

        return view('admin.lms-assessment-questions', compact('assessment', 'questions'));
    }

    public function storeQuestion(Request $request, string $assessmentId): RedirectResponse
    {
        $assessment = DB::table('lms_assessments')->where('id', $assessmentId)->first();
        abort_if($assessment === null, 404);

        $validated = $request->validate([
            'sequence' => ['required', 'integer', 'min:1'],
            'question_text' => ['required', 'string'],
            'type' => ['required', 'string', 'in:multiple_choice,essay'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:500'],
            'correct_option' => ['nullable', 'string', 'max:10'],
            'score_weight' => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($validated['type'] === 'multiple_choice') {
            $options = array_filter($validated['options'] ?? [], fn ($v) => trim((string) $v) !== '');

            if (count($options) < 2) {
                return back()->withInput()->with('gagal', 'Soal pilihan ganda butuh minimal 2 opsi.');
            }

            if (empty($validated['correct_option']) || ! isset($options[$validated['correct_option']])) {
                return back()->withInput()->with('gagal', 'Pilih opsi jawaban yang benar.');
            }
        }

        $sequenceTaken = DB::table('lms_assessment_questions')
            ->where('assessment_id', $assessmentId)
            ->where('sequence', $validated['sequence'])
            ->exists();

        if ($sequenceTaken) {
            return back()->withInput()->with('gagal', 'Urutan soal itu sudah dipakai.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_assessment_questions')->insert([
            'id' => $id,
            'assessment_id' => $assessmentId,
            'sequence' => $validated['sequence'],
            'question_text' => $validated['question_text'],
            'type' => $validated['type'],
            'options' => $validated['type'] === 'multiple_choice' ? json_encode($validated['options']) : null,
            'correct_option' => $validated['type'] === 'multiple_choice' ? $validated['correct_option'] : null,
            'score_weight' => $validated['score_weight'],
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_assessment_question',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: ['assessment_id' => $assessmentId, 'type' => $validated['type']],
        ));

        return redirect()->route('lms.admin.assessments.questions', $assessmentId)->with('sukses', 'Soal ditambahkan.');
    }

    public function destroyQuestion(Request $request, string $assessmentId, string $id): RedirectResponse
    {
        $question = DB::table('lms_assessment_questions')->where('id', $id)->where('assessment_id', $assessmentId)->first();
        abort_if($question === null, 404);

        DB::table('lms_assessment_questions')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_assessment_question',
            auditableId: $id,
            action: AuditAction::Deleted,
        ));

        return redirect()->route('lms.admin.assessments.questions', $assessmentId)->with('sukses', 'Soal dihapus.');
    }

    public function attempts(string $assessmentId): View
    {
        $assessment = DB::table('lms_assessments')->where('id', $assessmentId)->first();
        abort_if($assessment === null, 404);

        $attempts = DB::table('lms_assessment_attempts as at')
            ->join('emp_employees as e', 'e.id', '=', 'at.employee_id')
            ->where('at.assessment_id', $assessmentId)
            ->select('at.*', 'e.full_name', 'e.nrp')
            ->orderByDesc('at.started_at')
            ->get();

        return view('admin.lms-assessment-attempts', compact('assessment', 'attempts'));
    }

    public function grade(string $attemptId): View
    {
        $attempt = DB::table('lms_assessment_attempts as at')
            ->join('emp_employees as e', 'e.id', '=', 'at.employee_id')
            ->join('lms_assessments as a', 'a.id', '=', 'at.assessment_id')
            ->where('at.id', $attemptId)
            ->select('at.*', 'e.full_name', 'e.nrp', 'a.title as assessment_title')
            ->first();

        abort_if($attempt === null, 404);

        $answers = DB::table('lms_assessment_answers as an')
            ->join('lms_assessment_questions as q', 'q.id', '=', 'an.question_id')
            ->where('an.attempt_id', $attemptId)
            ->select('an.*', 'q.question_text', 'q.type', 'q.score_weight', 'q.sequence')
            ->orderBy('q.sequence')
            ->get();

        return view('admin.lms-assessment-grade', compact('attempt', 'answers'));
    }

    public function storeGrade(Request $request, string $attemptId): RedirectResponse
    {
        $validated = $request->validate([
            'skor' => ['required', 'array'],
            'skor.*' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->gradeAttempt->handle(
                attemptId: $attemptId,
                scoresByQuestionId: $validated['skor'],
                assessorId: (string) $this->actor->employeeId(),
                actor: $this->currentAuditActor($request),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('lms.admin.assessments.attempts', DB::table('lms_assessment_attempts')->where('id', $attemptId)->value('assessment_id'))
            ->with('sukses', 'Penilaian tersimpan.');
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
