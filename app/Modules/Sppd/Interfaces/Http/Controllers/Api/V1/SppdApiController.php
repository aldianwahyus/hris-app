<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Interfaces\Http\Controllers\Api\V1;

use App\Modules\Sppd\Application\SubmitSppdRequest;
use App\Modules\Sppd\Domain\JabatanTierNotMapped;
use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\SppdTariffNotFound;
use App\Modules\Sppd\Domain\TripCategory;
use App\Modules\Sppd\Interfaces\Http\Requests\SubmitSppdRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ESS Mobile (TOR Fase I) — cermin SppdRequestController, memakai
 * Application-layer yang SAMA (SubmitSppdRequest); seluruh komponen
 * anggaran tetap dihitung sistem, tidak diterima dari klien.
 */
final class SppdApiController
{
    public function __construct(private readonly SubmitSppdRequest $submit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $requests = DB::table('spd_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function store(SubmitSppdRequestForm $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $requestNumber = $this->submit->handle(
                employeeId: $user->employee_id,
                tripCategory: TripCategory::from($request->string('trip_category')->toString()),
                destination: (string) $request->string('destination'),
                purpose: (string) $request->string('purpose'),
                startDate: new DateTimeImmutable((string) $request->string('start_date')),
                endDate: new DateTimeImmutable((string) $request->string('end_date')),
                radiusBand: $request->filled('radius_band') ? RadiusBand::from($request->string('radius_band')->toString()) : null,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (JabatanTierNotMapped|SppdTariffNotFound|InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['request_number' => $requestNumber], 201);
    }
}
