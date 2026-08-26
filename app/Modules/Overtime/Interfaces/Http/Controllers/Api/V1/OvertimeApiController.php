<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Interfaces\Http\Controllers\Api\V1;

use App\Modules\Overtime\Application\SubmitOvertimeRequest;
use App\Modules\Overtime\Domain\NoOvertimeEvidence;
use App\Modules\Overtime\Domain\OvertimeType;
use App\Modules\Overtime\Interfaces\Http\Requests\SubmitOvertimeRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ESS Mobile (TOR Fase I) — cermin OvertimeRequestController, memakai
 * Application-layer yang SAMA (SubmitOvertimeRequest); jam lembur
 * TETAP dibaca dari bukti absensi (DEC-37), tidak ada kolom jam yang
 * diterima dari klien di sini.
 */
final class OvertimeApiController
{
    public function __construct(private readonly SubmitOvertimeRequest $submit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $requests = DB::table('ovt_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function store(SubmitOvertimeRequestForm $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $spklNumber = $this->submit->handle(
                employeeId: $user->employee_id,
                overtimeType: OvertimeType::from((string) $request->string('overtime_type')),
                workDate: new DateTimeImmutable((string) $request->string('work_date')),
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (NoOvertimeEvidence|DomainException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['spkl_number' => $spklNumber], 201);
    }
}
