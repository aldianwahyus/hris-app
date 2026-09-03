<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Modules\Employee\Application\ResolveEmployeeForHrAction;
use App\Modules\Offboarding\Application\CompleteClearanceItem;
use App\Modules\Offboarding\Application\DecideSeparation;
use App\Modules\Offboarding\Application\MarkSeparationComplete;
use App\Modules\Offboarding\Application\RequestSeparation;
use App\Modules\Offboarding\Application\SubmitExitInterview;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Offboarding — sisi HC. Maker-checker pola PERSIS antrean pegawai
 * baru: hr_admin/hr_approver mengajukan (maker), hr_approver
 * memutuskan (checker, tidak boleh pengajuan sendiri). hr_admin
 * lingkup kantornya sendiri, hr_approver seluruh bank.
 */
final class OffboardingQueueController extends Controller
{
    private const SEPARATION_TYPES = [
        'resign' => 'Mengundurkan Diri',
        'phk' => 'PHK',
        'pensiun' => 'Pensiun',
        'meninggal' => 'Meninggal Dunia',
        'kontrak_berakhir' => 'Kontrak Berakhir',
    ];

    public function __construct(
        private readonly CurrentActor $actor,
        private readonly RequestSeparation $requestSeparation,
        private readonly DecideSeparation $decide,
        private readonly CompleteClearanceItem $completeItem,
        private readonly MarkSeparationComplete $markComplete,
        private readonly SubmitExitInterview $submitExitInterview,
        private readonly ResolveEmployeeForHrAction $resolveEmployee,
    ) {}

    public function index(Request $request): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;
        $statusFilter = $request->string('status')->toString();

        $separations = DB::table('off_separation_requests as s')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->when($statusFilter !== '', fn ($q) => $q->where('s.status', $statusFilter))
            ->when($statusFilter === '', fn ($q) => $q->where('s.status', 'pending'))
            ->select('s.id', 's.separation_type', 's.requested_last_date', 's.status', 's.created_at', 'e.full_name', 'e.nrp')
            ->orderByDesc('s.created_at')
            ->get();

        return view('admin.offboarding-index', ['separations' => $separations, 'statusFilter' => $statusFilter, 'separationTypes' => self::SEPARATION_TYPES]);
    }

    public function create(): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $employees = DB::table('emp_employees')
            ->when($officeId !== null, fn ($q) => $q->where('office_id', $officeId))
            ->whereNull('separated_at')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nrp']);

        return view('admin.offboarding-create', ['employees' => $employees, 'separationTypes' => self::SEPARATION_TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'separation_type' => ['required', 'string', Rule::in(array_keys(self::SEPARATION_TYPES))],
            'reason' => ['required', 'string', 'max:1000'],
            'requested_last_date' => ['required', 'date'],
        ]);

        // Penegakan lingkup SERVER-SIDE (bukan cuma dropdown create()
        // yang dibatasi kantor) — pola PERSIS ResolveEmployeeForHrAction
        // yang sudah dipakai 5 controller HR-only lain, mencegah hr_admin
        // mengajukan pemisahan pegawai di luar kantornya lewat POST langsung.
        $this->resolveEmployee->handle($validated['employee_id'], $this->actor->roles(), $this->actor->officeId());

        try {
            $id = $this->requestSeparation->handle(
                employeeId: $validated['employee_id'],
                separationType: $validated['separation_type'],
                reason: $validated['reason'],
                requestedLastDate: new DateTimeImmutable($validated['requested_last_date']),
                requestedByEmployeeId: $actorEmployeeId,
                actor: $this->currentActor($request),
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.offboarding-show', $id)->with('sukses', 'Pengajuan pemisahan terkirim, menunggu persetujuan hr_approver.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->scopedSeparation($id);

        try {
            $this->decide->approve($id, $this->currentActor($request));
        } catch (DomainException $e) {
            return redirect()->route('admin.offboarding-show', $id)->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.offboarding-show', $id)->with('sukses', 'Pengajuan pemisahan disetujui, checklist clearance dibangkitkan.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $this->scopedSeparation($id);
        $note = $request->string('catatan')->toString();

        try {
            $this->decide->reject($id, $this->currentActor($request), $note !== '' ? $note : null);
        } catch (DomainException $e) {
            return redirect()->route('admin.offboarding-show', $id)->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.offboarding-index')->with('sukses', 'Pengajuan pemisahan ditolak.');
    }

    public function show(string $id): View
    {
        $separation = $this->scopedSeparation($id);
        $items = DB::table('off_clearance_items as i')
            ->leftJoin('emp_employees as d', 'd.id', '=', 'i.done_by')
            ->where('i.separation_id', $id)
            ->select('i.*', 'd.full_name as done_by_name')
            ->orderBy('i.category')
            ->get();

        $exitInterview = DB::table('off_exit_interviews')->where('separation_id', $id)->first();

        return view('admin.offboarding-show', [
            'separation' => $separation,
            'items' => $items,
            'exitInterview' => $exitInterview,
            'separationTypes' => self::SEPARATION_TYPES,
        ]);
    }

    public function completeItem(Request $request, string $separationId, string $itemId): RedirectResponse
    {
        $this->scopedSeparation($separationId);
        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate(['is_done' => ['required', 'boolean']]);

        try {
            $this->completeItem->handle($itemId, (bool) $validated['is_done'], $actorEmployeeId);
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.offboarding-show', $separationId)->with('sukses', 'Checklist diperbarui.');
    }

    public function markComplete(Request $request, string $id): RedirectResponse
    {
        $this->scopedSeparation($id);

        try {
            $this->markComplete->handle($id, $this->currentActor($request));
        } catch (DomainException $e) {
            return redirect()->route('admin.offboarding-show', $id)->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.offboarding-show', $id)->with('sukses', 'Pemisahan dituntaskan — akun pegawai dinonaktifkan.');
    }

    public function storeExitInterview(Request $request, string $id): RedirectResponse
    {
        $separation = $this->scopedSeparation($id);

        $validated = $request->validate([
            'reason_detail' => ['nullable', 'string', 'max:1000'],
            'satisfaction_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'would_recommend' => ['nullable', 'boolean'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->submitExitInterview->handle(
                separationId: $id,
                employeeId: $separation->employee_id,
                reasonDetail: $validated['reason_detail'] ?? null,
                satisfactionRating: $validated['satisfaction_rating'] ?? null,
                wouldRecommend: isset($validated['would_recommend']) ? (bool) $validated['would_recommend'] : null,
                comments: $validated['comments'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.offboarding-show', $id)->with('sukses', 'Wawancara keluar tersimpan.');
    }

    public function pendingCount(): int
    {
        if (! $this->actor->hasPermission('offboarding.manage')) {
            return 0;
        }

        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        return DB::table('off_separation_requests as s')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('s.status', 'pending')
            ->count();
    }

    /** @return object{id: string, employee_id: string, status: string, full_name: string, nrp: string} */
    private function scopedSeparation(string $id): object
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $separation = DB::table('off_separation_requests as s')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('s.id', $id)
            ->select('s.*', 'e.full_name', 'e.nrp')
            ->first();

        abort_if($separation === null, 404);

        /** @var object{id: string, employee_id: string, status: string, full_name: string, nrp: string} $separation */
        return $separation;
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
