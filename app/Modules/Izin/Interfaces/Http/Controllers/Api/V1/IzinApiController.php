<?php

declare(strict_types=1);

namespace App\Modules\Izin\Interfaces\Http\Controllers\Api\V1;

use App\Modules\Izin\Application\SubmitIzinRequest;
use App\Modules\Izin\Domain\IzinCategory;
use App\Modules\Izin\Interfaces\Http\Requests\SubmitIzinRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * ESS Mobile — cermin IzinRequestController, memakai Application-layer
 * yang SAMA (SubmitIzinRequest). Lampiran (mis. foto surat dokter untuk
 * kategori Sakit) dikirim sebagai multipart/form-data field 'attachment',
 * sama seperti jalur web.
 */
final class IzinApiController
{
    public function __construct(private readonly SubmitIzinRequest $submit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $requests = DB::table('izin_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function store(SubmitIzinRequestForm $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $attachmentPath = null;
        $attachmentOriginalName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $stored = $file->store('izin', 's3');

            abort_if($stored === false, 500, 'Gagal mengunggah lampiran — coba lagi.');

            $attachmentPath = $stored;
            $attachmentOriginalName = $file->getClientOriginalName();
        }

        try {
            $requestNumber = $this->submit->handle(
                employeeId: $user->employee_id,
                category: IzinCategory::from((string) $request->string('category')),
                startDate: new DateTimeImmutable((string) $request->string('start_date')),
                endDate: new DateTimeImmutable((string) $request->string('end_date')),
                reason: (string) $request->string('reason'),
                attachmentPath: $attachmentPath,
                attachmentOriginalName: $attachmentOriginalName,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
                isAdminHc: $user->hasRole('hr_approver'),
            );
        } catch (InvalidArgumentException|DomainException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['request_number' => $requestNumber], 201);
    }
}
