<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Whistleblowing\Application\ReviewReport;
use App\Modules\Whistleblowing\Application\SubmitReport;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean Whistleblowing/Pengaduan (Fase 2) — HANYA hr_approver
 * (permission:whistleblowing.manage, lihat migrasi permission),
 * bank-wide TANPA lingkup kantor (data sensitif, pola SAMA Privasi
 * Data).
 */
final class WhistleblowingQueueController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ReviewReport $review,
    ) {}

    public function pendingCount(): int
    {
        return DB::table('wb_reports')->whereIn('status', ['baru', 'diproses'])->count();
    }

    public function index(Request $request): View
    {
        $statusFilter = $request->string('status')->toString();

        $reports = DB::table('wb_reports as r')
            ->leftJoin('emp_employees as e', 'e.id', '=', 'r.reporter_employee_id')
            ->when($statusFilter !== '', fn ($q) => $q->where('r.status', $statusFilter))
            ->when($statusFilter === '', fn ($q) => $q->whereIn('r.status', ['baru', 'diproses']))
            ->select('r.id', 'r.category', 'r.is_anonymous', 'r.status', 'r.created_at', 'e.full_name', 'e.nrp')
            ->orderByDesc('r.created_at')
            ->get();

        return view('admin.whistleblowing-queue', [
            'reports' => $reports,
            'statusFilter' => $statusFilter,
            'categories' => SubmitReport::CATEGORIES,
        ]);
    }

    public function show(string $id): View
    {
        $report = DB::table('wb_reports as r')
            ->leftJoin('emp_employees as e', 'e.id', '=', 'r.reporter_employee_id')
            ->where('r.id', $id)
            ->select('r.*', 'e.full_name', 'e.nrp')
            ->first();

        abort_if($report === null, 404);

        return view('admin.whistleblowing-show', ['report' => $report, 'categories' => SubmitReport::CATEGORIES]);
    }

    public function startProcessing(string $id): RedirectResponse
    {
        try {
            $this->review->startProcessing($id, $this->auditActor());
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.whistleblowing-show', $id)->with('sukses', 'Laporan ditandai sedang ditindaklanjuti.');
    }

    public function complete(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->review->complete($id, $this->auditActor(), $validated['resolution_notes']);
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.whistleblowing-queue')->with('sukses', 'Laporan ditandai selesai ditangani.');
    }

    private function auditActor(): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
        );
    }
}
