<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\SubmitNewEmployeeRequest;
use App\Modules\Recruitment\Application\RecordInterviewFeedback;
use App\Modules\Recruitment\Application\ScheduleInterview;
use App\Modules\Recruitment\Application\UpdateApplicationStage;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pipeline kandidat per lowongan — HC ubah tahap, jadwalkan wawancara,
 * catat feedback, dan (setelah tawaran diterima) memproses kandidat
 * jadi pegawai baru lewat SubmitNewEmployeeRequest yang SUDAH ADA.
 *
 * convertToEmployee() SENGAJA di lapisan Interfaces (bukan kelas
 * Application "ConvertCandidateToEmployee" di modul Recruitment) —
 * modul Recruitment tidak boleh mengimpor
 * App\Modules\Employee\Application\* (ModuleBoundaryTest M-1), pola
 * PERSIS alasan GenerateOnboardingChecklist dipicu dari controller.
 * "Sudah dikonversi" TIDAK dilacak kolom terpisah — keunikan NRP yang
 * ditegakkan SubmitNewEmployeeRequest sendiri sudah mencegah konversi
 * ganda dengan NRP yang sama.
 */
final class ApplicationPipelineController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly UpdateApplicationStage $updateStage,
        private readonly ScheduleInterview $scheduleInterview,
        private readonly RecordInterviewFeedback $recordFeedback,
        private readonly SubmitNewEmployeeRequest $submitNewEmployee,
    ) {}

    public function index(string $postingId): View
    {
        $posting = DB::table('rec_job_postings')->where('id', $postingId)->first();
        abort_if($posting === null, 404);

        $applications = DB::table('rec_applications as a')
            ->join('rec_candidates as c', 'c.id', '=', 'a.candidate_id')
            ->where('a.posting_id', $postingId)
            ->select('a.id', 'a.status', 'a.applied_at', 'c.full_name', 'c.email')
            ->orderByDesc('a.applied_at')
            ->get();

        return view('admin.recruitment-pipeline-index', ['posting' => $posting, 'applications' => $applications]);
    }

    public function downloadResume(string $id): StreamedResponse
    {
        $application = $this->findApplication($id);
        abort_if($application->resume_path === null, 404, 'Kandidat ini tidak mengunggah CV.');

        return Storage::disk('s3')->download($application->resume_path);
    }

    public function show(string $id): View
    {
        $application = $this->findApplication($id);
        $interviews = DB::table('rec_interview_schedules as i')
            ->leftJoin('emp_employees as e', 'e.id', '=', 'i.interviewer_employee_id')
            ->where('i.application_id', $id)
            ->select('i.*', 'e.full_name as interviewer_name')
            ->orderByDesc('i.scheduled_at')
            ->get();
        $offers = DB::table('rec_job_offers as o')
            ->join('md_positions as p', 'p.id', '=', 'o.proposed_position_id')
            ->join('md_offices as of', 'of.id', '=', 'o.proposed_office_id')
            ->where('o.application_id', $id)
            ->select('o.*', 'p.name as position_name', 'of.name as office_name')
            ->orderByDesc('o.offered_at')
            ->get();

        $offices = DB::table('md_offices')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $positions = DB::table('md_positions')->orderBy('name')->get(['id', 'name']);
        $employees = DB::table('emp_employees')->orderBy('full_name')->get(['id', 'full_name', 'nrp']);

        return view('admin.recruitment-application-show', [
            'application' => $application,
            'interviews' => $interviews,
            'offers' => $offers,
            'offices' => $offices,
            'positions' => $positions,
            'employees' => $employees,
        ]);
    }

    public function updateStage(Request $request, string $id): RedirectResponse
    {
        $this->findApplication($id);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['melamar', 'seleksi_berkas', 'wawancara', 'penawaran', 'diterima', 'ditolak'])],
            'stage_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->updateStage->handle($id, $validated['status'], $validated['stage_notes'] ?? null);
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.recruitment-application-show', $id)->with('sukses', 'Tahap lamaran diperbarui.');
    }

    public function scheduleInterview(Request $request, string $id): RedirectResponse
    {
        $this->findApplication($id);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'location_or_link' => ['required', 'string', 'max:255'],
            'interviewer_employee_id' => ['nullable', 'uuid', 'exists:emp_employees,id'],
        ]);

        try {
            $this->scheduleInterview->handle(
                applicationId: $id,
                scheduledAt: new DateTimeImmutable($validated['scheduled_at']),
                locationOrLink: $validated['location_or_link'],
                interviewerEmployeeId: $validated['interviewer_employee_id'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.recruitment-application-show', $id)->with('sukses', 'Wawancara dijadwalkan.');
    }

    public function recordInterviewFeedback(Request $request, string $id, string $interviewId): RedirectResponse
    {
        $this->findApplication($id);

        $validated = $request->validate([
            'feedback' => ['required', 'string', 'max:2000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        try {
            $this->recordFeedback->handle($interviewId, $validated['feedback'], $validated['rating'] ?? null);
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.recruitment-application-show', $id)->with('sukses', 'Hasil wawancara tersimpan.');
    }

    /**
     * Unduh jadwal wawancara sebagai berkas .ics — PHP murni (TIDAK
     * ada API Google/Outlook, tidak butuh kredensial eksternal),
     * diimpor manual oleh kandidat/pewawancara ke kalender masing-masing.
     */
    public function downloadInterviewIcs(string $id, string $interviewId): Response
    {
        $application = $this->findApplication($id);

        $interview = DB::table('rec_interview_schedules')
            ->where('id', $interviewId)
            ->where('application_id', $id)
            ->first();

        abort_if($interview === null, 404);

        $start = new DateTimeImmutable($interview->scheduled_at);
        $end = $start->modify('+1 hour');
        $now = new DateTimeImmutable;

        $summary = "Wawancara — {$application->full_name} ({$application->posting_title})";

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Bank NTB Syariah//HCIS//ID',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:'.$interview->id.'@hcis.bankntbsyariah',
            'DTSTAMP:'.$now->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'DTSTART:'.$start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'DTEND:'.$end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escapeIcsText($summary),
            'LOCATION:'.$this->escapeIcsText($interview->location_or_link),
            'DESCRIPTION:'.$this->escapeIcsText("Wawancara kandidat untuk lowongan {$application->posting_title}."),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wawancara-'.$interview->id.'.ics"',
        ]);
    }

    private function escapeIcsText(string $value): string
    {
        return str_replace(['\\', "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\\;'], $value);
    }

    public function convertToEmployee(Request $request, string $id): RedirectResponse
    {
        $application = $this->findApplication($id);
        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        abort_unless($application->status === 'diterima', 422, 'Kandidat hanya dapat diproses jadi pegawai setelah tawaran diterima.');

        $offer = DB::table('rec_job_offers')
            ->where('application_id', $id)->where('status', 'diterima')
            ->orderByDesc('responded_at')->first();
        abort_if($offer === null, 422, 'Tidak ditemukan tawaran yang diterima untuk lamaran ini.');

        $posting = DB::table('rec_job_postings')->where('id', $application->posting_id)->first();
        abort_if($posting === null, 404);

        $validated = $request->validate([
            'nrp' => ['required', 'string', 'max:20'],
            'join_date' => ['required', 'date'],
        ]);

        try {
            $this->submitNewEmployee->handle(
                proposedData: [
                    'nrp' => $validated['nrp'],
                    'full_name' => $application->full_name,
                    'email' => $application->email,
                    'no_telepon' => $application->phone,
                    'join_date' => $validated['join_date'],
                    'employment_status' => $posting->employment_status_offered,
                    'office_id' => $offer->proposed_office_id,
                    'position_id' => $offer->proposed_position_id,
                ],
                requestedBy: $actorEmployeeId,
                actor: new AuditActor(
                    actorId: $actorEmployeeId,
                    actorRole: implode(',', $this->actor->roles()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.recruitment-application-show', $id)
            ->with('sukses', 'Kandidat diusulkan sebagai pegawai baru — menunggu persetujuan hr_approver di antrean pegawai baru.');
    }

    /** @return object{id: string, posting_id: string, candidate_id: string, status: string, stage_notes: ?string, applied_at: string, full_name: string, email: string, phone: ?string, resume_path: ?string, posting_title: string} */
    private function findApplication(string $id): object
    {
        $application = DB::table('rec_applications as a')
            ->join('rec_candidates as c', 'c.id', '=', 'a.candidate_id')
            ->join('rec_job_postings as jp', 'jp.id', '=', 'a.posting_id')
            ->where('a.id', $id)
            ->select('a.*', 'c.full_name', 'c.email', 'c.phone', 'c.resume_path', 'jp.title as posting_title')
            ->first();

        abort_if($application === null, 404);

        /** @var object{id: string, posting_id: string, candidate_id: string, status: string, stage_notes: ?string, applied_at: string, full_name: string, email: string, phone: ?string, resume_path: ?string, posting_title: string} $application */
        return $application;
    }
}
