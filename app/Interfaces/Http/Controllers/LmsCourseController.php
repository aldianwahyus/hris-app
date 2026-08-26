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
 * Katalog kursus LMS — HC (hr_admin/hr_approver/system_admin, sama
 * seperti Surat Keputusan). Tulis langsung, bukan maker-checker: ini
 * definisi katalog, bukan keputusan bisnis per pegawai — pola sama
 * ShiftPatternController.
 */
final class LmsCourseController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $courses = DB::table('lms_courses')
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get();

        $batches = DB::table('lms_course_batches')
            ->orderBy('start_date')
            ->get()
            ->groupBy('course_id');

        $enrolledCounts = DB::table('lms_enrollments')
            ->select('batch_id', DB::raw('count(*) as jumlah'))
            ->whereIn('status', ['pending', 'approved'])
            ->groupBy('batch_id')
            ->pluck('jumlah', 'batch_id');

        return view('admin.lms-courses', compact('courses', 'batches', 'enrolledCounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $codeTaken = DB::table('lms_courses')->where('code', $validated['code'])->whereNull('deleted_at')->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode kursus itu sudah dipakai.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_courses')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'is_active' => true,
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_course',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.courses.index')->with('sukses', 'Kursus tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $course = DB::table('lms_courses')->where('id', $id)->first();

        abort_if($course === null, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('lms_courses')->where('id', $id)->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $course->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_course',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $course,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.courses.index')->with('sukses', 'Kursus diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $course = DB::table('lms_courses')->where('id', $id)->first();

        abort_if($course === null, 404);

        $hasBatches = DB::table('lms_course_batches')->where('course_id', $id)->exists();

        if ($hasBatches) {
            return back()->with('gagal', 'Kursus ini masih punya jadwal kelas — tidak dapat dihapus.');
        }

        DB::table('lms_courses')->where('id', $id)->update(['deleted_at' => new DateTimeImmutable]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_course',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['code' => $course->code, 'title' => $course->title],
        ));

        return redirect()->route('lms.admin.courses.index')->with('sukses', 'Kursus dihapus.');
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
