<?php

declare(strict_types=1);

namespace App\Modules\Shift\Interfaces\Http\Controllers;

use App\Modules\Shift\Application\CancelShiftSwapRequest;
use App\Modules\Shift\Application\SubmitShiftSwapRequest;
use App\Modules\Shift\Interfaces\Http\Requests\SubmitShiftSwapRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

final class ShiftSwapRequestController
{
    public function __construct(
        private readonly SubmitShiftSwapRequest $submit,
        private readonly CancelShiftSwapRequest $cancel,
    ) {}

    /** Riwayat Tukar Shift Saya — SEMUA pengajuan milik pegawai yang login. */
    public function history(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $requests = DB::table('shf_swap_requests as r')
            ->join('emp_employees as c', 'c.id', '=', 'r.counterpart_employee_id')
            ->select('r.*', 'c.full_name as counterpart_name')
            ->where('r.requesting_employee_id', $user->employee_id)
            ->orderByDesc('r.created_at')
            ->paginate(20);

        return view('shift.history', compact('requests'));
    }

    public function cancelRequest(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $this->cancel->handle(
                shiftSwapRequestId: $id,
                employeeId: $user->employee_id,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', 'Pengajuan tukar shift berhasil dibatalkan.');
    }

    public function create(): View
    {
        $employeeId = auth()->user()?->employee_id;

        $colleagues = DB::table('emp_employees')
            ->where('id', '!=', $employeeId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nrp']);

        return view('shift.create', compact('colleagues'));
    }

    public function store(SubmitShiftSwapRequestForm $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $requestNumber = $this->submit->handle(
                requestingEmployeeId: $user->employee_id,
                counterpartEmployeeId: (string) $request->string('counterpart_employee_id'),
                swapDate: new DateTimeImmutable((string) $request->string('swap_date')),
                reason: $request->string('reason')->toString() !== '' ? (string) $request->string('reason') : null,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (InvalidArgumentException|DomainException|RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()
            ->route('ess.dashboard')
            ->with('sukses', "Pengajuan tukar shift {$requestNumber} berhasil dikirim dan menunggu persetujuan.");
    }
}
