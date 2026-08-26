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
 * Daftar Jabatan — rujukan tunggal `md_positions` dipakai Data Pegawai,
 * SK, Lembur (kelayakan lembur = atribut jabatan), dst. SYSADMIN/Admin
 * HC, bank-wide — sama kategori dengan Formasi Kantor. Jabatan TIDAK
 * BISA DIHAPUS, hanya dinonaktifkan (`is_active`) — lihat docblock
 * OfficeController untuk alasan tidak memakai `deleted_at`.
 */
final class PositionController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $positions = DB::table('md_positions')
            ->orderBy('name')
            ->get();

        return view('admin.position-directory', compact('positions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:150'],
            'classification' => ['nullable', 'string', 'in:business,support'],
            'job_grade_min' => ['nullable', 'integer', 'min:1', 'max:255'],
            'job_grade_max' => ['nullable', 'integer', 'min:1', 'max:255', 'gte:job_grade_min'],
            'eligible_overtime_regular' => ['nullable', 'boolean'],
            'eligible_overtime_crash' => ['nullable', 'boolean'],
            'overtime_rate_class' => ['nullable', 'string', 'in:MGR_SPV_OFC,ASST_ADM,NON_ADMIN'],
        ]);

        $codeTaken = DB::table('md_positions')->where('code', $validated['code'])->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode jabatan itu sudah dipakai.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('md_positions')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'classification' => $validated['classification'] ?? null,
            'job_grade_min' => $validated['job_grade_min'] ?? null,
            'job_grade_max' => $validated['job_grade_max'] ?? null,
            'eligible_overtime_regular' => $validated['eligible_overtime_regular'] ?? true,
            'eligible_overtime_crash' => $validated['eligible_overtime_crash'] ?? true,
            'overtime_rate_class' => $validated['overtime_rate_class'] ?? null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'md_position',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.positions.index')->with('sukses', 'Jabatan tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $position = DB::table('md_positions')->where('id', $id)->first();

        abort_if($position === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'classification' => ['nullable', 'string', 'in:business,support'],
            'job_grade_min' => ['nullable', 'integer', 'min:1', 'max:255'],
            'job_grade_max' => ['nullable', 'integer', 'min:1', 'max:255', 'gte:job_grade_min'],
            'eligible_overtime_regular' => ['nullable', 'boolean'],
            'eligible_overtime_crash' => ['nullable', 'boolean'],
            'overtime_rate_class' => ['nullable', 'string', 'in:MGR_SPV_OFC,ASST_ADM,NON_ADMIN'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('md_positions')->where('id', $id)->update([
            'name' => $validated['name'],
            'classification' => $validated['classification'] ?? null,
            'job_grade_min' => $validated['job_grade_min'] ?? null,
            'job_grade_max' => $validated['job_grade_max'] ?? null,
            'eligible_overtime_regular' => $validated['eligible_overtime_regular'] ?? false,
            'eligible_overtime_crash' => $validated['eligible_overtime_crash'] ?? false,
            'overtime_rate_class' => $validated['overtime_rate_class'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $position->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'md_position',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $position,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.positions.index')->with('sukses', "Jabatan {$position->name} diperbarui.");
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
