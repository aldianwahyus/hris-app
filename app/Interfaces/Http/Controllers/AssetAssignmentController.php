<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Asset\Application\AssignAsset;
use App\Modules\Asset\Application\ReturnAsset;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Penugasan/pengembalian aset — terpisah dari AssetController (katalog)
 * karena ini mengubah STATE (siapa memegang aset apa sekarang), bukan
 * sekadar data master.
 */
final class AssetAssignmentController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AssignAsset $assign,
        private readonly ReturnAsset $return,
    ) {}

    public function assign(Request $request, string $assetId): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:emp_employees,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $this->assign->handle(
                assetId: $assetId,
                employeeId: $validated['employee_id'],
                assignedByEmployeeId: $actorEmployeeId,
                notes: $validated['notes'] ?? null,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', 'Aset berhasil ditugaskan.');
    }

    public function returnAsset(Request $request, string $assignmentId): RedirectResponse
    {
        $validated = $request->validate([
            'returned_condition' => ['required', 'string', 'in:baik,rusak_ringan,rusak_berat'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->return->handle(
                assignmentId: $assignmentId,
                returnedCondition: $validated['returned_condition'],
                notes: $validated['notes'] ?? null,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', 'Pengembalian aset tercatat.');
    }

    /** ESS — "Aset Saya", baca saja: aset yang sedang dipegang pegawai yang login. */
    public function mine(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $assignments = DB::table('ast_assignments as asg')
            ->join('ast_assets as a', 'a.id', '=', 'asg.asset_id')
            ->where('asg.employee_id', $user->employee_id)
            ->whereNull('asg.returned_at')
            ->select('a.asset_code', 'a.name', 'a.category', 'a.brand_model', 'a.serial_number', 'asg.assigned_at')
            ->orderBy('asg.assigned_at')
            ->get();

        return view('ess.my-assets', compact('assignments'));
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
