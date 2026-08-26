<?php

declare(strict_types=1);

namespace App\Modules\Access\Interfaces\Http\Controllers;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LogoutController
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->employee_id !== null) {
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
                action: AuditAction::LoggedOut,
            ));
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
