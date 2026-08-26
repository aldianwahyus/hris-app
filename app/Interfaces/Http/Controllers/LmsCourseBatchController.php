<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Lms\Application\RecordCourseCompletion;
use App\Modules\Lms\Application\RecordSessionAttendance;
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
 * Jadwal kelas (batch) di bawah satu kursus, + roster pendaftar disetujui
 * dan pencatatan kelulusan/nilai — HC (hr_admin/hr_approver/system_admin).
 * CRUD batch: pola sama LmsCourseController/ShiftPatternController.
 * recordCompletion(): delegasi ke RecordCourseCompletion (Application),
 * yang juga menulis-turun ke emp_trainings/emp_certifications saat lulus.
 */
final class LmsCourseBatchController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
        private readonly RecordCourseCompletion $recordCompletion,
        private readonly RecordSessionAttendance $recordAttendance,
    ) {}

    public function store(Request $request, string $courseId): RedirectResponse
    {
        $course = DB::table('lms_courses')->where('id', $courseId)->whereNull('deleted_at')->first();
        abort_if($course === null, 404);

        $validated = $request->validate([
            'batch_code' => ['required', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:200'],
            'instructor_name' => ['nullable', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:start_date'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $codeTaken = DB::table('lms_course_batches')
            ->where('course_id', $courseId)
            ->where('batch_code', $validated['batch_code'])
            ->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode jadwal itu sudah dipakai untuk kursus ini.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_course_batches')->insert([
            'id' => $id,
            'course_id' => $courseId,
            'batch_code' => $validated['batch_code'],
            'location' => $validated['location'] ?? null,
            'instructor_name' => $validated['instructor_name'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'status' => 'scheduled',
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_course_batch',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.courses.index')->with('sukses', 'Jadwal kelas tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $batch = DB::table('lms_course_batches')->where('id', $id)->first();
        abort_if($batch === null, 404);

        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:200'],
            'instructor_name' => ['nullable', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:start_date'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:scheduled,ongoing,completed,cancelled'],
        ]);

        DB::table('lms_course_batches')->where('id', $id)->update([
            'location' => $validated['location'] ?? null,
            'instructor_name' => $validated['instructor_name'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'status' => $validated['status'],
            'updated_at' => new DateTimeImmutable,
            'version' => $batch->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_course_batch',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $batch,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.courses.index')->with('sukses', 'Jadwal kelas diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $batch = DB::table('lms_course_batches')->where('id', $id)->first();
        abort_if($batch === null, 404);

        $hasEnrollments = DB::table('lms_enrollments')->where('batch_id', $id)->exists();

        if ($hasEnrollments) {
            return back()->with('gagal', 'Jadwal kelas ini sudah punya pendaftar — tidak dapat dihapus.');
        }

        DB::table('lms_course_batches')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_course_batch',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['batch_code' => $batch->batch_code],
        ));

        return redirect()->route('lms.admin.courses.index')->with('sukses', 'Jadwal kelas dihapus.');
    }

    public function roster(string $id): View
    {
        $batch = DB::table('lms_course_batches as b')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->where('b.id', $id)
            ->select('b.*', 'c.title as course_title')
            ->first();

        abort_if($batch === null, 404);

        // SEMUA status ditampilkan (pending/approved/rejected/cancelled) —
        // BUKAN cuma approved/rejected seperti sebelumnya. Bug: kolom
        // "Kuota" di LmsCourseController::index() menghitung
        // pending+approved (pendaftar yang masih memegang kursi), tapi
        // roster ini dulu HANYA menampilkan approved+rejected — pendaftar
        // yang belum diputuskan Atasan Langsung ikut menghitung kuota TAPI
        // tidak pernah terlihat di sini. Form "Catat Kelulusan" tetap
        // HANYA muncul untuk baris berstatus approved (lihat view).
        $enrollments = DB::table('lms_enrollments as en')
            ->join('emp_employees as e', 'e.id', '=', 'en.employee_id')
            ->where('en.batch_id', $id)
            ->select('en.*', 'e.full_name', 'e.nrp')
            ->orderBy('e.full_name')
            ->get();

        return view('admin.lms-batch-roster', compact('batch', 'enrollments'));
    }

    public function recordCompletion(Request $request, string $enrollmentId): RedirectResponse
    {
        $validated = $request->validate([
            'completion_status' => ['required', 'string', 'in:lulus,tidak_lulus'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $enrollment = DB::table('lms_enrollments')->where('id', $enrollmentId)->first();
        abort_if($enrollment === null, 404);

        try {
            $this->recordCompletion->handle(
                enrollmentId: $enrollmentId,
                completionStatus: $validated['completion_status'],
                score: isset($validated['score']) ? (string) $validated['score'] : null,
                actor: $this->currentAuditActor($request),
                recordedBy: (string) $this->actor->employeeId(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('lms.admin.batches.roster', $enrollment->batch_id)
            ->with('sukses', 'Kelulusan tercatat.');
    }

    public function sessions(string $batchId): View
    {
        $batch = DB::table('lms_course_batches as b')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->where('b.id', $batchId)
            ->select('b.*', 'c.title as course_title')
            ->first();

        abort_if($batch === null, 404);

        $sessions = DB::table('lms_course_sessions')
            ->where('batch_id', $batchId)
            ->orderBy('sequence')
            ->get();

        return view('admin.lms-batch-sessions', compact('batch', 'sessions'));
    }

    public function storeSession(Request $request, string $batchId): RedirectResponse
    {
        $batch = DB::table('lms_course_batches')->where('id', $batchId)->first();
        abort_if($batch === null, 404);

        $validated = $request->validate([
            'sequence' => ['required', 'integer', 'min:1'],
            'session_date' => ['required', 'date'],
            'topic' => ['nullable', 'string', 'max:200'],
        ]);

        $sequenceTaken = DB::table('lms_course_sessions')
            ->where('batch_id', $batchId)
            ->where('sequence', $validated['sequence'])
            ->exists();

        if ($sequenceTaken) {
            return back()->withInput()->with('gagal', 'Urutan sesi itu sudah dipakai untuk jadwal ini.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_course_sessions')->insert([
            'id' => $id,
            'batch_id' => $batchId,
            'sequence' => $validated['sequence'],
            'session_date' => $validated['session_date'],
            'topic' => $validated['topic'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_course_session',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.batches.sessions', $batchId)->with('sukses', 'Sesi tersimpan.');
    }

    public function attendance(string $sessionId): View
    {
        $session = DB::table('lms_course_sessions as s')
            ->join('lms_course_batches as b', 'b.id', '=', 's.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->where('s.id', $sessionId)
            ->select('s.*', 'b.batch_code', 'c.title as course_title')
            ->first();

        abort_if($session === null, 404);

        // HANYA pendaftar approved yang dapat diabsen — pola sama
        // pencatatan kelulusan (RecordCourseCompletion), lihat guard di
        // RecordSessionAttendance.
        $enrollments = DB::table('lms_enrollments as en')
            ->join('emp_employees as e', 'e.id', '=', 'en.employee_id')
            ->leftJoin('lms_attendances as a', fn ($q) => $q->on('a.enrollment_id', '=', 'en.id')->where('a.session_id', $sessionId))
            ->where('en.batch_id', $session->batch_id)
            ->where('en.status', 'approved')
            ->select('en.id as enrollment_id', 'e.full_name', 'e.nrp', 'a.status as attendance_status')
            ->orderBy('e.full_name')
            ->get();

        return view('admin.lms-session-attendance', compact('session', 'enrollments'));
    }

    public function storeAttendance(Request $request, string $sessionId): RedirectResponse
    {
        $session = DB::table('lms_course_sessions')->where('id', $sessionId)->first();
        abort_if($session === null, 404);

        $validated = $request->validate([
            'kehadiran' => ['required', 'array'],
            'kehadiran.*' => ['required', 'string', 'in:hadir,izin,sakit,alpa'],
        ]);

        try {
            $this->recordAttendance->handle(
                sessionId: $sessionId,
                statusesByEnrollmentId: $validated['kehadiran'],
                actor: $this->currentAuditActor($request),
                recordedBy: (string) $this->actor->employeeId(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('lms.admin.sessions.attendance', $sessionId)->with('sukses', 'Absensi tercatat.');
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
