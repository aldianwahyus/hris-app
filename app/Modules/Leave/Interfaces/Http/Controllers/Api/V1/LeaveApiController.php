<?php

declare(strict_types=1);

namespace App\Modules\Leave\Interfaces\Http\Controllers\Api\V1;

use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\FirstLeaveMustBeBlock;
use App\Modules\Leave\Domain\InsufficientLeaveBalance;
use App\Modules\Leave\Domain\LeaveType;
use App\Modules\Leave\Interfaces\Http\Requests\SubmitLeaveRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ESS Mobile (TOR Fase I) — cermin LeaveRequestController, memakai
 * Application-layer yang SAMA (SubmitLeaveRequest); hanya bentuk
 * responsnya (JSON, bukan redirect+flash) yang berbeda.
 */
final class LeaveApiController
{
    public function __construct(private readonly SubmitLeaveRequest $submit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $requests = DB::table('leave_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function store(SubmitLeaveRequestForm $request): JsonResponse
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
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (InsufficientLeaveBalance|FirstLeaveMustBeBlock|DomainException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['request_number' => $requestNumber], 201);
    }
}
