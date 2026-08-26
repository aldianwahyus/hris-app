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
 * Digital Library — pengelolaan (HC: hr_admin/hr_approver/system_admin,
 * permission:lms-catalog.manage yang SUDAH ADA). index() juga menampilkan
 * jumlah akses per item (agregat lms_library_access_logs) — bagian
 * "tracking aktivitas" yang terlihat HC, lihat LmsLibraryController::open()
 * untuk sisi pencatatannya.
 */
final class LmsLibraryAdminController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $items = DB::table('lms_library_items as li')
            ->leftJoin('lms_courses as c', 'c.id', '=', 'li.course_id')
            ->select('li.*', 'c.title as course_title')
            ->orderByDesc('li.created_at')
            ->get();

        $accessCounts = DB::table('lms_library_access_logs')
            ->select('library_item_id', DB::raw('count(*) as jumlah'))
            ->groupBy('library_item_id')
            ->pluck('jumlah', 'library_item_id');

        $courses = DB::table('lms_courses')->whereNull('deleted_at')->orderBy('title')->get(['id', 'title']);

        return view('admin.lms-library-admin', compact('items', 'accessCounts', 'courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'course_id' => ['nullable', 'uuid', 'exists:lms_courses,id'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'berkas' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx', 'max:20480'],
        ]);

        $hasFile = $request->hasFile('berkas');
        $hasUrl = ! empty($validated['external_url']);

        if (! $hasFile && ! $hasUrl) {
            return back()->withInput()->with('gagal', 'Unggah berkas atau isi tautan eksternal — salah satu wajib diisi.');
        }

        $filePath = null;
        $fileOriginalName = null;

        if ($hasFile) {
            $file = $request->file('berkas');
            $stored = $file->store('lms/library', 's3');

            abort_if($stored === false, 500, 'Gagal mengunggah berkas — coba lagi.');

            $filePath = $stored;
            $fileOriginalName = $file->getClientOriginalName();
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_library_items')->insert([
            'id' => $id,
            'course_id' => $validated['course_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'file_path' => $filePath,
            'file_original_name' => $fileOriginalName,
            'external_url' => $validated['external_url'] ?? null,
            'uploaded_by' => $this->actor->employeeId(),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_library_item',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: ['title' => $validated['title'], 'category' => $validated['category'] ?? null],
        ));

        return redirect()->route('lms.admin.library.index')->with('sukses', 'Materi tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $item = DB::table('lms_library_items')->where('id', $id)->first();
        abort_if($item === null, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('lms_library_items')->where('id', $id)->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $item->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_library_item',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: ['title' => $item->title, 'is_active' => $item->is_active],
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.library.index')->with('sukses', "Materi \"{$validated['title']}\" diperbarui.");
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
