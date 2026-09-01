<?php

declare(strict_types=1);

namespace App\Modules\Leave\Interfaces\Http\Controllers;

use App\Modules\Leave\Application\CancelLeaveRequest;
use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\FirstLeaveMustBeBlock;
use App\Modules\Leave\Domain\InsufficientLeaveBalance;
use App\Modules\Leave\Domain\LeaveType;
use App\Modules\Leave\Interfaces\Http\Requests\SubmitLeaveRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

final class LeaveRequestController
{
    public function __construct(
        private readonly SubmitLeaveRequest $submit,
        private readonly CancelLeaveRequest $cancel,
    ) {}

    public function create(): View
    {
        return view('leave.create');
    }

    /** Riwayat Cuti Saya — SEMUA pengajuan milik pegawai yang login, tidak dibatasi seperti daftar 3 baris di dashboard. */
    public function history(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $requests = DB::table('leave_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('leave.history', compact('requests'));
    }

    public function cancelRequest(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $this->cancel->handle(
                leaveRequestId: $id,
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

        return back()->with('sukses', 'Pengajuan cuti berhasil dibatalkan.');
    }

    public function store(SubmitLeaveRequestForm $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $requestNumber = $this->submit->handle(
                employeeId: $user->employee_id,
                leaveType: LeaveType::CutiTahunan,
                startDate: new DateTimeImmutable((string) $request->string('start_date')),
                endDate: new DateTimeImmutable((string) $request->string('end_date')),
                reason: $request->string('reason')->toString() !== '' ? (string) $request->string('reason') : null,
                actor: new AuditActor(
                    // Audit trail memakai employee_id (uuid), sejalan dengan
                    // approver_id/initiator_id di seluruh skema — bukan id
                    // akun login (bigint), yang bertipe berbeda dari kolom
                    // actor_id (uuid) pada aud_change_logs.
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (InsufficientLeaveBalance|FirstLeaveMustBeBlock|DomainException|RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()
            ->route('ess.dashboard')
            ->with('sukses', "Pengajuan cuti {$requestNumber} berhasil dikirim dan menunggu persetujuan.");
    }
}
