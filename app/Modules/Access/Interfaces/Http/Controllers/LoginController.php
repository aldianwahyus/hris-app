<?php

declare(strict_types=1);

namespace App\Modules\Access\Interfaces\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Application\AuthenticateEmployee;
use App\Modules\Access\Interfaces\Http\Requests\LoginRequest;
use App\Modules\Employee\Contracts\EmployeeRepository;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Masuk melalui sesi (cookie) — dipakai antarmuka web Blade dan,
 * kelak, SPA Vue lewat mode stateful Sanctum (DEC-02: satu-satunya
 * identity provider, satu jalur verifikasi kredensial).
 */
final class LoginController
{
    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly AuditRepository $audit,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticateEmployee $auth): RedirectResponse
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

        Auth::login($user, $request->boolean('ingat'));
        $request->session()->regenerate();

        $this->recordSuccessfulLogin($request, $user);

        // Admin Sistem bukan pegawai SDM sungguhan (lihat Role::SystemAdmin)
        // — beranda pegawai tidak relevan baginya.
        $landing = $user->hasRole('system_admin') ? 'sysadmin.users.index' : 'ess.dashboard';

        return redirect()->intended(route($landing));
    }

    private function recordSuccessfulLogin(LoginRequest $request, User $user): void
    {
        if ($user->employee_id === null) {
            return;
        }

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
        ));
    }

    /**
     * SEC-2026-08: sebelumnya TIDAK ADA jejak audit login/logout sama
     * sekali — Auditor (BANK_WIDE, §6.3) tidak punya visibilitas atas
     * peristiwa autentikasi. auditable_id WAJIB UUID pegawai yang
     * valid (kolom DB bertipe UUID) — bila NRP tidak dikenal sama
     * sekali, tidak ada UUID pegawai untuk dicatat; percobaan itu
     * tetap tertahan RateLimiter (LoginRequest), hanya tidak masuk
     * jejak audit PERSISTEN. Pesan galat TETAP seragam ("NRP atau
     * kata sandi salah") terlepas dari cabang ini — pencatatan
     * terjadi di server, tidak pernah tercermin ke respons, sehingga
     * TIDAK membuka kanal enumerasi baru.
     */
    private function recordFailedLogin(LoginRequest $request, string $nrp): void
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
