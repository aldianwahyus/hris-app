<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Lms\Application\AwardGamificationPoints;
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
 * Gamifikasi (BRD §5.8) — HC (permission:lms-catalog.manage): kelola
 * badge, kelola challenge, beri badge manual, tandai peserta challenge
 * selesai (memicu poin lewat AwardGamificationPoints). Poin dari
 * kelulusan kursus/asesmen OTOMATIS (lihat RecordCourseCompletion/
 * SubmitAssessmentAttempt/GradeAssessmentAttempt), tidak lewat sini.
 */
final class GamificationController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
        private readonly AwardGamificationPoints $awardPoints,
    ) {}

    public function badgesIndex(): View
    {
        $badges = DB::table('lms_badges')->orderBy('name')->get();

        $awardCounts = DB::table('lms_employee_badges')
            ->select('badge_id', DB::raw('count(*) as jumlah'))
            ->groupBy('badge_id')
            ->pluck('jumlah', 'badge_id');

        $employees = DB::table('emp_employees')->orderBy('full_name')->get(['id', 'full_name', 'nrp']);

        return view('admin.lms-badges', compact('badges', 'awardCounts', 'employees'));
    }

    public function storeBadge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:10'],
        ]);

        $codeTaken = DB::table('lms_badges')->where('code', $validated['code'])->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode lencana itu sudah dipakai.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_badges')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_badge',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.badges.index')->with('sukses', 'Lencana tersimpan.');
    }

    public function awardBadge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'badge_id' => ['required', 'uuid', 'exists:lms_badges,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $alreadyAwarded = DB::table('lms_employee_badges')
            ->where('employee_id', $validated['employee_id'])
            ->where('badge_id', $validated['badge_id'])
            ->exists();

        if ($alreadyAwarded) {
            return back()->with('gagal', 'Pegawai ini sudah memiliki lencana tersebut.');
        }

        $now = new DateTimeImmutable;

        DB::table('lms_employee_badges')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $validated['employee_id'],
            'badge_id' => $validated['badge_id'],
            'awarded_at' => $now,
            'awarded_by' => $this->actor->employeeId(),
            'notes' => $validated['notes'] ?? null,
            'created_at' => $now,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_employee_badge',
            auditableId: $validated['employee_id'],
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.badges.index')->with('sukses', 'Lencana diberikan.');
    }

    public function challengesIndex(): View
    {
        $challenges = DB::table('lms_challenges')->orderByDesc('created_at')->get();

        $participantCounts = DB::table('lms_challenge_participants')
            ->select('challenge_id', DB::raw('count(*) as jumlah'))
            ->groupBy('challenge_id')
            ->pluck('jumlah', 'challenge_id');

        return view('admin.lms-challenges', compact('challenges', 'participantCounts'));
    }

    public function storeChallenge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'points_reward' => ['required', 'integer', 'min:0'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_challenges')->insert([
            'id' => $id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'points_reward' => $validated['points_reward'],
            'is_active' => true,
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_challenge',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.challenges.index')->with('sukses', 'Challenge tersimpan.');
    }

    public function challengeParticipants(string $challengeId): View
    {
        $challenge = DB::table('lms_challenges')->where('id', $challengeId)->first();
        abort_if($challenge === null, 404);

        $participants = DB::table('lms_challenge_participants as cp')
            ->join('emp_employees as e', 'e.id', '=', 'cp.employee_id')
            ->where('cp.challenge_id', $challengeId)
            ->select('cp.*', 'e.full_name', 'e.nrp')
            ->orderBy('e.full_name')
            ->get();

        return view('admin.lms-challenge-participants', compact('challenge', 'participants'));
    }

    public function markChallengeCompleted(Request $request, string $challengeId, string $employeeId): RedirectResponse
    {
        $challenge = DB::table('lms_challenges')->where('id', $challengeId)->first();
        abort_if($challenge === null, 404);

        $participant = DB::table('lms_challenge_participants')
            ->where('challenge_id', $challengeId)
            ->where('employee_id', $employeeId)
            ->first();

        abort_if($participant === null, 404);

        if ($participant->status === 'completed') {
            return back()->with('gagal', 'Peserta ini sudah ditandai selesai sebelumnya.');
        }

        $now = new DateTimeImmutable;

        DB::table('lms_challenge_participants')->where('id', $participant->id)->update([
            'status' => 'completed',
            'completed_at' => $now,
            'updated_at' => $now,
            'version' => $participant->version + 1,
        ]);

        if ($challenge->points_reward > 0) {
            $this->awardPoints->handle(
                employeeId: $employeeId,
                points: $challenge->points_reward,
                reason: "Menyelesaikan challenge: {$challenge->title}",
                sourceType: 'lms_challenge',
                sourceId: $challengeId,
            );
        }

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_challenge_participant',
            auditableId: $participant->id,
            action: AuditAction::Updated,
            newValues: ['status' => 'completed'],
        ));

        return redirect()->route('lms.admin.challenges.participants', $challengeId)->with('sukses', 'Peserta ditandai selesai.');
    }

    public function leaderboard(): View
    {
        $rows = DB::table('lms_gamification_points as p')
            ->join('emp_employees as e', 'e.id', '=', 'p.employee_id')
            ->select('e.id', 'e.full_name', 'e.nrp', DB::raw('sum(p.points) as total_poin'))
            ->groupBy('e.id', 'e.full_name', 'e.nrp')
            ->orderByDesc('total_poin')
            ->limit(50)
            ->get();

        return view('admin.lms-leaderboard', compact('rows'));
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
