<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Modules\Onboarding\Application\CompleteChecklistItem;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dasbor progres Onboarding — checklist dibangkitkan otomatis oleh
 * GenerateOnboardingChecklist saat pegawai baru disetujui. hr_admin
 * lingkup kantornya sendiri, hr_approver seluruh bank.
 */
final class OnboardingProgressController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly CompleteChecklistItem $completeItem,
    ) {}

    public function index(Request $request): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;
        $showCompleted = $request->boolean('selesai');

        $checklists = DB::table('onb_employee_checklists as c')
            ->join('emp_employees as e', 'e.id', '=', 'c.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->when(! $showCompleted, fn ($q) => $q->whereNull('c.completed_at'))
            ->when($showCompleted, fn ($q) => $q->whereNotNull('c.completed_at'))
            ->select('c.id', 'c.started_at', 'c.completed_at', 'e.full_name', 'e.nrp')
            ->orderBy('c.started_at')
            ->get();

        $itemStats = DB::table('onb_employee_checklist_items')
            ->select('checklist_id', DB::raw('count(*) as total'), DB::raw('sum(case when is_done then 1 else 0 end) as selesai'))
            ->whereIn('checklist_id', $checklists->pluck('id'))
            ->groupBy('checklist_id')
            ->get()
            ->keyBy('checklist_id');

        return view('admin.onboarding-progress-index', ['checklists' => $checklists, 'itemStats' => $itemStats, 'showCompleted' => $showCompleted]);
    }

    public function show(string $id): View
    {
        $checklist = $this->scopedChecklist($id);
        $items = DB::table('onb_employee_checklist_items as i')
            ->leftJoin('emp_employees as d', 'd.id', '=', 'i.done_by')
            ->where('i.checklist_id', $id)
            ->select('i.*', 'd.full_name as done_by_name')
            ->orderBy('i.category')
            ->get();

        return view('admin.onboarding-progress-show', ['checklist' => $checklist, 'items' => $items]);
    }

    public function completeItem(Request $request, string $checklistId, string $itemId): RedirectResponse
    {
        $this->scopedChecklist($checklistId);
        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'is_done' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->completeItem->handle($itemId, (bool) $validated['is_done'], $actorEmployeeId, $validated['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.onboarding-show', $checklistId)->with('sukses', 'Checklist diperbarui.');
    }

    /** @return object{id: string, employee_id: string, started_at: string, completed_at: ?string} */
    private function scopedChecklist(string $id): object
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $checklist = DB::table('onb_employee_checklists as c')
            ->join('emp_employees as e', 'e.id', '=', 'c.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('c.id', $id)
            ->select('c.*', 'e.full_name', 'e.nrp')
            ->first();

        abort_if($checklist === null, 404);

        /** @var object{id: string, employee_id: string, started_at: string, completed_at: ?string} $checklist */
        return $checklist;
    }
}
