<?php

declare(strict_types=1);

namespace App\Modules\Shift\Interfaces\Http\Controllers;

use App\Modules\Shift\Application\SubmitShiftSwapRequest;
use App\Modules\Shift\Interfaces\Http\Requests\SubmitShiftSwapRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

final class ShiftSwapRequestController
{
    public function __construct(private readonly SubmitShiftSwapRequest $submit) {}

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
