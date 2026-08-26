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
 * Pola shift (definisi jam kerja bergilir) — SYSADMIN/Admin HC,
 * bank-wide. Tulis langsung (BUKAN maker-checker): ini definisi jadwal,
 * bukan keputusan bisnis SDM per pegawai — sama kategori dengan
 * OfficeGeofenceController.
 */
final class ShiftPatternController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $patterns = DB::table('shf_shift_patterns')
            ->select('id', 'code', 'name', 'start_time', 'end_time', 'crosses_midnight', 'is_active')
            ->orderBy('start_time')
            ->get();

        return view('admin.shift-patterns', compact('patterns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'crosses_midnight' => ['nullable', 'boolean'],
        ]);

        $codeTaken = DB::table('shf_shift_patterns')->where('code', $validated['code'])->whereNull('deleted_at')->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode pola shift itu sudah dipakai.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('shf_shift_patterns')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'crosses_midnight' => $validated['crosses_midnight'] ?? false,
            'is_active' => true,
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'shift_pattern',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.shift-patterns.index')->with('sukses', 'Pola shift tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $pattern = DB::table('shf_shift_patterns')->where('id', $id)->first();

        abort_if($pattern === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'crosses_midnight' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('shf_shift_patterns')->where('id', $id)->update([
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'crosses_midnight' => $validated['crosses_midnight'] ?? false,
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $pattern->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'shift_pattern',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $pattern,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.shift-patterns.index')->with('sukses', 'Pola shift diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $pattern = DB::table('shf_shift_patterns')->where('id', $id)->first();

        abort_if($pattern === null, 404);

        $stillAssigned = DB::table('shf_employee_assignments')
            ->where('shift_pattern_id', $id)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->format('Y-m-d')))
            ->exists();

        if ($stillAssigned) {
            return back()->with('gagal', 'Pola shift ini masih dipakai penugasan aktif — tidak dapat dihapus.');
        }

        DB::table('shf_shift_patterns')->where('id', $id)->update(['deleted_at' => new DateTimeImmutable]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'shift_pattern',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['code' => $pattern->code, 'name' => $pattern->name],
        ));

        return redirect()->route('sysadmin.shift-patterns.index')->with('sukses', 'Pola shift dihapus.');
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
