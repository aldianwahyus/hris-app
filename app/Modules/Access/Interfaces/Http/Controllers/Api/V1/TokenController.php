<?php

declare(strict_types=1);

namespace App\Modules\Access\Interfaces\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Access\Application\AuthenticateEmployee;
use App\Modules\Access\Interfaces\Http\Requests\ApiLoginRequest;
use App\Modules\Employee\Contracts\EmployeeRepository;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Penerbitan token akses pribadi (Sanctum) untuk klien non-SPA
 * (mis. aplikasi mobile). Memakai jalur verifikasi kredensial yang
 * SAMA dengan LoginController (DEC-02) — hanya cara menyerahkan
 * identitas ke klien yang berbeda: token, bukan cookie sesi.
 */
final class TokenController
{
    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly AuditRepository $audit,
    ) {}

    public function store(ApiLoginRequest $request, AuthenticateEmployee $auth): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $nrp = (string) $request->string('nrp');
        $user = $auth->verify($nrp, (string) $request->string('password'));

        if ($user === null) {
            $request->recordFailedAttempt();
            $this->recordFailedLogin($request, $nrp);

            throw ValidationException::withMessages([
                'nrp' => 'NRP atau kata sandi salah.',
            ]);
        }

        $request->clearAttempts();

        $deviceName = $request->string('nama_perangkat')->toString() !== ''
            ? (string) $request->string('nama_perangkat')
            : ($request->userAgent() ?? 'perangkat-tidak-dikenal');

        $token = $user->createToken($deviceName, $user->getRoleNames()->all());

        if ($user->employee_id !== null) {
            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
                auditableType: 'user_account',
                auditableId: $user->employee_id,
                action: AuditAction::LoginSucceeded,
                newValues: ['perangkat' => $deviceName],
            ));
        }

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->getKey(),
                'nrp' => $user->employee?->nrp,
                'nama' => $user->employee?->full_name,
                'roles' => $user->getRoleNames(),
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        // Diselesaikan langsung dari string bearer, bukan dari
        // currentAccessToken(): guard Sanctum memberi prioritas pada sesi
        // web bila keduanya hadir, dan dapat mengembalikan TransientToken
        // (tidak dapat dihapus) meski permintaan ini membawa token asli.
        $accessToken = PersonalAccessToken::findToken((string) $request->bearerToken());
        $owner = $accessToken?->tokenable;

        $accessToken?->delete();

        if ($owner instanceof User && $owner->employee_id !== null) {
            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: new AuditActor(
                    actorId: $owner->employee_id,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
                auditableType: 'user_account',
                auditableId: $owner->employee_id,
                action: AuditAction::LoggedOut,
            ));
        }

        return response()->json(status: 204);
    }

    /**
     * Sama seperti LoginController::recordFailedLogin() — auditable_id
     * WAJIB UUID pegawai valid, sehingga NRP yang sama sekali tidak
     * dikenal tidak menghasilkan entri audit persisten (tetap tertahan
     * RateLimiter di LoginRequest).
     */
    private function recordFailedLogin(ApiLoginRequest $request, string $nrp): void
    {
        $employee = $this->employees->findByNrp($nrp);

        if ($employee === null) {
            return;
        }

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: new AuditActor(
                actorId: $employee->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            auditableType: 'user_account',
            auditableId: $employee->id,
            action: AuditAction::LoginFailed,
        ));
    }
}
