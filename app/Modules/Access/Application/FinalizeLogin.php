<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use App\Models\User;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Menuntaskan sesi login SETELAH seluruh syarat terpenuhi — kredensial
 * (SELALU) dan kode 2FA (bila akun itu butuh). SATU titik dipakai
 * ULANG oleh LoginController (jalur tanpa 2FA) MAUPUN TwoFactorController
 * (jalur dengan 2FA) supaya `Auth::login()`+regenerasi sesi+jejak audit
 * TIDAK terduplikasi/menyimpang di dua tempat.
 */
final class FinalizeLogin
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(Request $request, User $user, bool $remember): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

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

    /** Admin Sistem tidak punya beranda pegawai yang berarti — pola SAMA LoginController lama. */
    public function landingRoute(User $user): string
    {
        return $user->hasRole('system_admin') ? 'sysadmin.users.index' : 'ess.dashboard';
    }
}
