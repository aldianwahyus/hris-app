<?php

declare(strict_types=1);

namespace App\Modules\Survey\Interfaces\Http\Controllers\Api\V1;

use App\Modules\Survey\Application\SubmitSurveyResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** ESS Mobile — cermin SurveyController, memakai Application-layer yang SAMA (SubmitSurveyResponse). */
final class SurveyApiController
{
    public function __construct(private readonly SubmitSurveyResponse $submit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $officeId = DB::table('emp_employees')->where('id', $user->employee_id)->value('office_id');
        $today = now()->format('Y-m-d');

        $surveys = DB::table('svy_surveys')
            ->where('status', 'aktif')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->where(fn ($q) => $q->where('scope', 'bank_wide')->orWhere('office_id', $officeId))
            ->orderByDesc('created_at')
            ->get();

        $respondedIds = DB::table('svy_response_tokens')
            ->where('employee_id', $user->employee_id)
            ->pluck('survey_id')
            ->all();

        return response()->json(['data' => $surveys, 'responded_ids' => $respondedIds]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $survey = $this->eligibleSurvey($id, $user->employee_id);

        $alreadyResponded = DB::table('svy_response_tokens')
            ->where('survey_id', $id)->where('employee_id', $user->employee_id)->exists();
        abort_if($alreadyResponded, 403, 'Anda sudah pernah mengisi survei ini.');

        $questions = DB::table('svy_questions')->where('survey_id', $id)->orderBy('display_order')->get()
            ->map(function ($q) {
                $q->options = $q->options_json !== null ? (json_decode($q->options_json, true) ?? []) : [];

                return $q;
            });

        return response()->json(['data' => $survey, 'questions' => $questions]);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $this->eligibleSurvey($id, $user->employee_id);

        $questionIds = DB::table('svy_questions')->where('survey_id', $id)->pluck('id');
        $answers = [];

        foreach ($questionIds as $questionId) {
            $answers[$questionId] = trim((string) $request->input("jawaban.{$questionId}", ''));
        }

        try {
            $this->submit->handle($id, $user->employee_id, $answers);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Terima kasih, jawaban Anda telah terkirim.']);
    }

    /** @return object{id: string, title: string, description: ?string, is_anonymous: bool, end_date: string} */
    private function eligibleSurvey(string $id, string $employeeId): object
    {
        $officeId = DB::table('emp_employees')->where('id', $employeeId)->value('office_id');
        $today = now()->format('Y-m-d');

        $survey = DB::table('svy_surveys')
            ->where('id', $id)
            ->where('status', 'aktif')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->where(fn ($q) => $q->where('scope', 'bank_wide')->orWhere('office_id', $officeId))
            ->first();

        abort_if($survey === null, 404);

        /** @var object{id: string, title: string, description: ?string, is_anonymous: bool, end_date: string} $survey */
        return $survey;
    }
}
