<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Modules\Survey\Application\ComputeSurveyResults;
use App\Modules\Survey\Application\CreateSurvey;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Survei Keterlibatan (eNPS/Pulse) — sisi HC. hr_admin hanya
 * mengelola survei kantornya sendiri + melihat yang bank-wide,
 * hr_approver mengelola seluruhnya (pola PERSIS antrean lain).
 */
final class SurveyAdminController extends Controller
{
    private const QUESTION_TYPES = ['nps_0_10', 'rating_1_5', 'teks', 'pilihan_ganda'];

    public function __construct(
        private readonly CurrentActor $actor,
        private readonly CreateSurvey $create,
        private readonly ComputeSurveyResults $results,
    ) {}

    public function index(): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $surveys = DB::table('svy_surveys')
            ->when($officeId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('scope', 'bank_wide')->orWhere('office_id', $officeId)))
            ->orderByDesc('created_at')
            ->get();

        return view('admin.survey-index', ['surveys' => $surveys]);
    }

    public function create(): View
    {
        $offices = DB::table('md_offices')->orderBy('name')->get();

        return view('admin.survey-create', ['offices' => $offices, 'questionTypes' => self::QUESTION_TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(['enps', 'pulse', 'kustom'])],
            'scope' => ['required', 'string', Rule::in(['bank_wide', 'office'])],
            'office_id' => ['nullable', 'required_if:scope,office', 'uuid', 'exists:md_offices,id'],
            'is_anonymous' => ['nullable', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string', 'max:500'],
            'questions.*.question_type' => ['required', 'string', Rule::in(self::QUESTION_TYPES)],
            'questions.*.options' => ['nullable', 'string', 'max:1000'],
        ]);

        $questions = array_map(function (array $q) {
            $options = null;

            if ($q['question_type'] === 'pilihan_ganda' && ! empty($q['options'])) {
                $options = json_encode(array_values(array_filter(array_map('trim', explode(',', $q['options']))))) ?: null;
            }

            return [
                'question_text' => (string) $q['question_text'],
                'question_type' => (string) $q['question_type'],
                'options_json' => $options,
            ];
        }, $validated['questions']);

        try {
            $surveyId = $this->create->handle(
                title: $validated['title'],
                description: $validated['description'] ?? null,
                type: $validated['type'],
                scope: $validated['scope'],
                officeId: $validated['office_id'] ?? null,
                isAnonymous: (bool) ($validated['is_anonymous'] ?? false),
                startDate: new DateTimeImmutable($validated['start_date']),
                endDate: new DateTimeImmutable($validated['end_date']),
                questions: $questions,
                createdByEmployeeId: $actorEmployeeId,
                actor: new AuditActor(
                    actorId: $actorEmployeeId,
                    actorRole: implode(',', $this->actor->roles()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.survey-show', $surveyId)->with('sukses', 'Survei berhasil dibuat sebagai draf.');
    }

    public function show(string $id): View
    {
        $survey = $this->scopedSurvey($id);
        $questions = DB::table('svy_questions')->where('survey_id', $id)->orderBy('display_order')->get();
        $results = $this->results->handle($id);

        return view('admin.survey-show', ['survey' => $survey, 'questions' => $questions, 'results' => $results]);
    }

    public function publish(string $id): RedirectResponse
    {
        $survey = $this->scopedSurvey($id);
        abort_unless($survey->status === 'draft', 422, 'Hanya survei berstatus draf yang dapat diterbitkan.');

        DB::table('svy_surveys')->where('id', $id)->update([
            'status' => 'aktif',
            'updated_at' => new DateTimeImmutable,
            'version' => $survey->version + 1,
        ]);

        return redirect()->route('admin.survey-show', $id)->with('sukses', 'Survei diterbitkan dan dapat diisi pegawai.');
    }

    public function close(string $id): RedirectResponse
    {
        $survey = $this->scopedSurvey($id);
        abort_unless($survey->status === 'aktif', 422, 'Hanya survei aktif yang dapat ditutup.');

        DB::table('svy_surveys')->where('id', $id)->update([
            'status' => 'selesai',
            'updated_at' => new DateTimeImmutable,
            'version' => $survey->version + 1,
        ]);

        return redirect()->route('admin.survey-show', $id)->with('sukses', 'Survei ditutup.');
    }

    /** @return object{id: string, status: string, scope: string, office_id: ?string, version: int} */
    private function scopedSurvey(string $id): object
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $survey = DB::table('svy_surveys')
            ->when($officeId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('scope', 'bank_wide')->orWhere('office_id', $officeId)))
            ->where('id', $id)
            ->first();

        abort_if($survey === null, 404);

        /** @var object{id: string, status: string, scope: string, office_id: ?string, version: int} $survey */
        return $survey;
    }
}
