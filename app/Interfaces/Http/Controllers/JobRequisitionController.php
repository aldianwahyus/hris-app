<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Modules\Recruitment\Application\DecideJobRequisition;
use App\Modules\Recruitment\Application\SubmitJobRequisition;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Job Requisition — maker-checker pola PERSIS pengajuan pegawai baru.
 * hr_admin/hr_approver mengajukan (`recruitment.manage`), HANYA
 * hr_approver memutuskan (`recruitment-requisition.decide`, permission
 * TERPISAH — beda dari modul lain sesi ini yang satu permission untuk
 * maker+checker, di sini sengaja dipisah karena requisition membuka
 * anggaran tenaga kerja, keputusan lebih sensitif dari operasional
 * ATS sehari-hari).
 */
final class JobRequisitionController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly SubmitJobRequisition $submit,
        private readonly DecideJobRequisition $decide,
    ) {}

    public function index(Request $request): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;
        $statusFilter = $request->string('status')->toString();

        $requisitions = DB::table('rec_job_requisitions as r')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->join('md_positions as p', 'p.id', '=', 'r.position_id')
            ->when($officeId !== null, fn ($q) => $q->where('r.office_id', $officeId))
            ->when($statusFilter !== '', fn ($q) => $q->where('r.status', $statusFilter))
            ->when($statusFilter === '', fn ($q) => $q->where('r.status', 'pending'))
            ->select('r.id', 'r.requested_headcount', 'r.status', 'r.created_at', 'o.name as office_name', 'p.name as position_name')
            ->orderByDesc('r.created_at')
            ->get();

        return view('admin.recruitment-requisition-index', ['requisitions' => $requisitions, 'statusFilter' => $statusFilter]);
    }

    public function create(): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $offices = DB::table('md_offices')
            ->when($officeId !== null, fn ($q) => $q->where('id', $officeId))
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $positions = DB::table('md_positions')->orderBy('name')->get(['id', 'name']);

        return view('admin.recruitment-requisition-create', ['offices' => $offices, 'positions' => $positions]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'office_id' => ['required', 'uuid', 'exists:md_offices,id'],
            'position_id' => ['required', 'uuid', 'exists:md_positions,id'],
            'requested_headcount' => ['required', 'integer', 'min:1', 'max:50'],
            'justification' => ['required', 'string', 'max:1000'],
        ]);

        if ($this->actor->hasRole(Role::HrAdmin->value) && $validated['office_id'] !== $this->actor->officeId()) {
            abort(403, 'Anda hanya dapat mengajukan requisition untuk kantor Anda sendiri.');
        }

        $id = $this->submit->handle(
            officeId: $validated['office_id'],
            positionId: $validated['position_id'],
            requestedHeadcount: (int) $validated['requested_headcount'],
            justification: $validated['justification'],
            requestedByEmployeeId: $actorEmployeeId,
            actor: $this->currentActor($request),
        );

        return redirect()->route('admin.recruitment-requisition-show', $id)->with('sukses', 'Requisition terkirim, menunggu persetujuan hr_approver.');
    }

    public function show(string $id): View
    {
        $requisition = $this->scopedRequisition($id);

        return view('admin.recruitment-requisition-show', ['requisition' => $requisition]);
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        try {
            $this->decide->approve($id, $this->currentActor($request));
        } catch (DomainException $e) {
            return redirect()->route('admin.recruitment-requisition-show', $id)->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.recruitment-requisition-show', $id)->with('sukses', 'Requisition disetujui — lowongan dapat dibuka.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $note = $request->string('catatan')->toString();

        try {
            $this->decide->reject($id, $this->currentActor($request), $note !== '' ? $note : null);
        } catch (DomainException $e) {
            return redirect()->route('admin.recruitment-requisition-show', $id)->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.recruitment-requisition-index')->with('sukses', 'Requisition ditolak.');
    }

    public function pendingCount(): int
    {
        if (! $this->actor->hasPermission('recruitment-requisition.decide')) {
            return 0;
        }

        return DB::table('rec_job_requisitions')->where('status', 'pending')->count();
    }

    /** @return object{id: string, office_id: string, status: string} */
    private function scopedRequisition(string $id): object
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $requisition = DB::table('rec_job_requisitions as r')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->join('md_positions as p', 'p.id', '=', 'r.position_id')
            ->when($officeId !== null, fn ($q) => $q->where('r.office_id', $officeId))
            ->where('r.id', $id)
            ->select('r.*', 'o.name as office_name', 'p.name as position_name')
            ->first();

        abort_if($requisition === null, 404);

        /** @var object{id: string, office_id: string, status: string} $requisition */
        return $requisition;
    }

    private function currentActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
