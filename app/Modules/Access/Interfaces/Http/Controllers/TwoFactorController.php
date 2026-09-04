<?php

declare(strict_types=1);

namespace App\Modules\Access\Interfaces\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Application\FinalizeLogin;
use App\Modules\Access\Application\SetupTwoFactor;
use App\Modules\Access\Application\VerifyTwoFactorCode;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tantangan + setup wajib 2FA (TOTP) — Fase 2. Dijangkau HANYA dari
 * LoginController::store() setelah kredensial NRP+kata sandi benar
 * (sesi "menggantung" via `2fa_pending_user_id`, BUKAN login penuh —
 * Auth::check() masih false sampai FinalizeLogin dipanggil). Setiap
 * method di sini WAJIB menolak akses langsung tanpa sesi menggantung
 * itu (abort_if), supaya rute ini tidak jadi jalan pintas melewati
 * kredensial.
 */
final class TwoFactorController
{
    public function __construct(
        private readonly VerifyTwoFactorCode $verify,
        private readonly SetupTwoFactor $setup,
        private readonly FinalizeLogin $finalize,
    ) {}

    public function showChallenge(Request $request): View
    {
        $user = $this->pendingUser($request);
        abort_if($user->two_factor_confirmed_at === null, 404);

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        abort_if($user->two_factor_confirmed_at === null, 404);

        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);

        if (! $this->verify->handle($user, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'Kode otentikasi tidak valid.']);
        }

        return $this->completeLogin($request, $user);
    }

    public function showSetup(Request $request): View
    {
        $user = $this->pendingUser($request);
        abort_if($user->two_factor_confirmed_at !== null, 404);

        [$secret, $qrSvg] = $this->setup->prepare($user);
        $request->session()->put('2fa_setup_secret', $secret);

        return view('auth.two-factor-setup', ['qrSvg' => $qrSvg, 'secret' => $secret]);
    }

    public function confirmSetup(Request $request): View
    {
        $user = $this->pendingUser($request);
        abort_if($user->two_factor_confirmed_at !== null, 404);

        $secret = $request->session()->get('2fa_setup_secret');
        abort_if(! is_string($secret) || $secret === '', 403, 'Sesi setup 2FA kedaluwarsa, mulai ulang.');

        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);

        try {
            $recoveryCodes = $this->setup->confirm($user, $secret, $validated['code']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $request->session()->forget('2fa_setup_secret');
        $remember = (bool) $request->session()->get('2fa_remember', false);
        $request->session()->forget(['2fa_pending_user_id', '2fa_remember']);

        $this->finalize->handle($request, $user, $remember);

        return view('auth.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
            'landingRoute' => $this->finalize->landingRoute($user),
        ]);
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        $remember = (bool) $request->session()->get('2fa_remember', false);
        $request->session()->forget(['2fa_pending_user_id', '2fa_remember']);

        $this->finalize->handle($request, $user, $remember);

        return redirect()->intended(route($this->finalize->landingRoute($user)));
    }

    private function pendingUser(Request $request): User
    {
        $userId = $request->session()->get('2fa_pending_user_id');
        abort_if($userId === null, 403, 'Tidak ada proses masuk yang sedang berjalan.');

        $user = User::query()->where('id', $userId)->first();
        abort_if($user === null, 403);

        return $user;
    }
}
