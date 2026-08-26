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
 * Live Learning & Mentoring (BRD §5.10) — HC
 * (permission:lms-catalog.manage). Penjadwalan + tautan rapat/rekaman
 * eksternal (TIDAK ada hosting video sendiri, lihat docblock migrasi).
 */
final class LiveSessionController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $sessions = DB::table('lms_live_sessions as s')
            ->leftJoin('lms_courses as c', 'c.id', '=', 's.course_id')
            ->leftJoin('emp_employees as f', 'f.id', '=', 's.facilitator_employee_id')
            ->select('s.*', 'c.title as course_title', 'f.full_name as facilitator_name')
            ->orderByDesc('s.scheduled_at')
            ->get();

        $participantCounts = DB::table('lms_live_session_participants')
            ->select('session_id', DB::raw('count(*) as jumlah'))
            ->groupBy('session_id')
            ->pluck('jumlah', 'session_id');

        $courses = DB::table('lms_courses')->whereNull('deleted_at')->orderBy('title')->get(['id', 'title']);
        $employees = DB::table('emp_employees')->orderBy('full_name')->get(['id', 'full_name']);

        return view('admin.lms-live-sessions', compact('sessions', 'participantCounts', 'courses', 'employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'session_type' => ['required', 'string', 'in:webinar,coaching,mentoring'],
            'course_id' => ['nullable', 'uuid', 'exists:lms_courses,id'],
            'facilitator_employee_id' => ['nullable', 'uuid', 'exists:emp_employees,id'],
            'scheduled_at' => ['required', 'date'],
            'meeting_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_live_sessions')->insert([
            'id' => $id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'session_type' => $validated['session_type'],
            'course_id' => $validated['course_id'] ?? null,
            'facilitator_employee_id' => $validated['facilitator_employee_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'meeting_url' => $validated['meeting_url'] ?? null,
            'is_active' => true,
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_live_session',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.live-sessions.index')->with('sukses', 'Sesi tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $session = DB::table('lms_live_sessions')->where('id', $id)->first();
        abort_if($session === null, 404);

        $validated = $request->validate([
            'meeting_url' => ['nullable', 'url', 'max:2048'],
            'recording_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('lms_live_sessions')->where('id', $id)->update([
            'meeting_url' => $validated['meeting_url'] ?? null,
            'recording_url' => $validated['recording_url'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $session->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_live_session',
            auditableId: $id,
            action: AuditAction::Updated,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.live-sessions.index')->with('sukses', 'Sesi diperbarui.');
    }

    public function participants(string $id): View
    {
        $session = DB::table('lms_live_sessions')->where('id', $id)->first();
        abort_if($session === null, 404);

        $participants = DB::table('lms_live_session_participants as p')
            ->join('emp_employees as e', 'e.id', '=', 'p.employee_id')
            ->where('p.session_id', $id)
            ->select('p.*', 'e.full_name', 'e.nrp')
            ->orderBy('e.full_name')
            ->get();

        return view('admin.lms-live-session-participants', compact('session', 'participants'));
    }

    public function markAttended(Request $request, string $sessionId, string $employeeId): RedirectResponse
    {
        $participant = DB::table('lms_live_session_participants')
            ->where('session_id', $sessionId)
            ->where('employee_id', $employeeId)
            ->first();

        abort_if($participant === null, 404);

        DB::table('lms_live_session_participants')->where('id', $participant->id)->update([
            'status' => 'attended',
            'updated_at' => new DateTimeImmutable,
            'version' => $participant->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_live_session_participant',
            auditableId: $participant->id,
            action: AuditAction::Updated,
            newValues: ['status' => 'attended'],
        ));

        return redirect()->route('lms.admin.live-sessions.participants', $sessionId)->with('sukses', 'Kehadiran tercatat.');
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
