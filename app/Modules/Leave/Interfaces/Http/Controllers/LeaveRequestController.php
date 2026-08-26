<?php

declare(strict_types=1);

namespace App\Modules\Leave\Interfaces\Http\Controllers;

use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\FirstLeaveMustBeBlock;
use App\Modules\Leave\Domain\InsufficientLeaveBalance;
use App\Modules\Leave\Domain\LeaveType;
use App\Modules\Leave\Interfaces\Http\Requests\SubmitLeaveRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

final class LeaveRequestController
{
    public function __construct(private readonly SubmitLeaveRequest $submit) {}

    public function create(): View
    {
        return view('leave.create');
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
