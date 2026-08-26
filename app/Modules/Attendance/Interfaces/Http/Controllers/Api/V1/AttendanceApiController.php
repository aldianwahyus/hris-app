<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Http\Controllers\Api\V1;

use App\Modules\Attendance\Application\RecordGpsAttendance;
use App\Modules\Attendance\Domain\AlreadyCompletedToday;
use App\Modules\Attendance\Domain\AttendanceAction;
use App\Modules\Attendance\Domain\AttendanceActionNotAllowed;
use App\Modules\Attendance\Domain\BreakNotYetAllowed;
use App\Modules\Attendance\Domain\GeoCoordinate;
use App\Modules\Attendance\Domain\OutsideGeofence;
use App\Modules\Attendance\Interfaces\Http\Requests\RecordGpsAttendanceForm;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * ESS Mobile (TOR Fase I) — cermin AttendanceController, memakai
 * Application-layer yang SAMA (RecordGpsAttendance). Klien menyatakan
 * maksudnya secara eksplisit lewat field 'action' (masuk/istirahat/
 * kembali/pulang) — lihat docblock RecordGpsAttendance untuk alasan
 * urutan scan saja tidak lagi cukup sejak Istirahat opsional ditambahkan.
 */
final class AttendanceApiController
{
    public function __construct(private readonly RecordGpsAttendance $record) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $riwayat = DB::table('att_attendance_records')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('work_date')
            ->limit(14)
            ->get();

        return response()->json(['data' => $riwayat]);
    }

    public function store(RecordGpsAttendanceForm $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $point = new GeoCoordinate((float) $request->input('latitude'), (float) $request->input('longitude'));

            $outcome = $this->record->handle(
                employeeId: $user->employee_id,
                point: $point,
                action: AttendanceAction::from($request->string('action')->toString()),
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (OutsideGeofence|AlreadyCompletedToday|AttendanceActionNotAllowed|BreakNotYetAllowed|InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'action' => $outcome->action->value,
            'status' => $outcome->status?->value,
        ], 201);
    }
}
