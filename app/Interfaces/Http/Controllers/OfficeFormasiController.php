<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

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
 * Formasi/kuota pegawai resmi per kantor — SYSADMIN/Admin HC, dasar
 * perhitungan GAP di Analitik SDM (HcDashboardController). Tulis
 * langsung (BUKAN maker-checker): ini target staffing, bukan keputusan
 * bisnis per pegawai — sama kategori dengan OfficeGeofenceController.
 */
final class OfficeFormasiController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $offices = DB::table('md_offices as o')
            ->leftJoin('emp_employees as e', 'e.office_id', '=', 'o.id')
            ->selectRaw('o.id, o.code, o.name, o.office_type, o.authorized_headcount, count(e.id) as actual_headcount')
            ->groupBy('o.id', 'o.code', 'o.name', 'o.office_type', 'o.authorized_headcount')
            ->orderBy('o.name')
            ->get();

        return view('admin.office-formasi', compact('offices'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'authorized_headcount' => ['required', 'integer', 'min:0'],
        ]);

        $office = DB::table('md_offices')->where('id', $id)->first();

        abort_if($office === null, 404);

        DB::table('md_offices')->where('id', $id)->update([
            'authorized_headcount' => $validated['authorized_headcount'],
            'updated_at' => new DateTimeImmutable,
            'version' => $office->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: new AuditActor(
                actorId: $this->actor->employeeId(),
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            auditableType: 'md_office',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: ['authorized_headcount' => $office->authorized_headcount],
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.office-formasi.index')
            ->with('sukses', "Formasi {$office->name} tersimpan.");
    }
}
