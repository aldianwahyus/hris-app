<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Lms\Application\SubmitAssessmentAttempt;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Online Assessment — ESS, TANPA middleware permission (semua pegawai
 * boleh mengerjakan asesmen aktif, pola sama LmsEnrollmentController).
 */
final class LmsAssessmentController extends Controller
{
    public function __construct(private readonly SubmitAssessmentAttempt $submit) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $assessments = DB::table('lms_assessments as a')
            ->leftJoin('lms_courses as c', 'c.id', '=', 'a.course_id')
            ->where('a.is_active', true)
            ->select('a.id', 'a.title', 'a.description', 'a.duration_minutes', 'a.passing_score', 'c.title as course_title')
            ->orderBy('a.title')
            ->get();

        $myAttempts = DB::table('lms_assessment_attempts')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('started_at')
            ->get()
            ->groupBy('assessment_id');

        return view('lms.assessment-index', compact('assessments', 'myAttempts'));
    }

    public function start(Request $request, string $assessmentId): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $attemptId = $this->submit->start($assessmentId, $user->employee_id);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('lms.assessment.index')->with('gagal', $e->getMessage());
        }

        return redirect()->route('lms.assessment.take', $attemptId);
    }

    public function take(Request $request, string $attemptId): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $attempt = DB::table('lms_assessment_attempts as at')
            ->join('lms_assessments as a', 'a.id', '=', 'at.assessment_id')
            ->where('at.id', $attemptId)
            ->where('at.employee_id', $user->employee_id)
            ->select('at.*', 'a.title as assessment_title', 'a.duration_minutes')
            ->first();

        abort_if($attempt === null, 404);
        abort_if($attempt->status !== 'in_progress', 404, 'Asesmen ini sudah dikirim.');

        $questions = DB::table('lms_assessment_questions')
            ->where('assessment_id', $attempt->assessment_id)
            ->orderBy('sequence')
            ->get()
            ->map(function ($q) {
                $q->options = $q->options !== null ? json_decode($q->options, true) : null;

                return $q;
            });

        return view('lms.assessment-take', compact('attempt', 'questions'));
    }

    public function submit(Request $request, string $attemptId): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $owns = DB::table('lms_assessment_attempts')->where('id', $attemptId)->where('employee_id', $user->employee_id)->exists();
        abort_if(! $owns, 404);

        $jawaban = $request->input('jawaban', []);

        try {
            $this->submit->submit($attemptId, $jawaban, new AuditActor(
                actorId: $user->employee_id,
                actorRole: implode(',', $user->getRoleNames()->all()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (InvalidArgumentException $e) {
            return redirect()->route('lms.assessment.index')->with('gagal', $e->getMessage());
        }

        return redirect()->route('lms.assessment.result', $attemptId);
    }

    public function result(Request $request, string $attemptId): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $attempt = DB::table('lms_assessment_attempts as at')
            ->join('lms_assessments as a', 'a.id', '=', 'at.assessment_id')
            ->where('at.id', $attemptId)
            ->where('at.employee_id', $user->employee_id)
            ->select('at.*', 'a.title as assessment_title', 'a.passing_score')
            ->first();

        abort_if($attempt === null, 404);

        return view('lms.assessment-result', compact('attempt'));
    }
}
