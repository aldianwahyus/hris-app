<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Memverifikasi kode TOTP ATAU kode pemulihan pada tantangan login.
 * Kode pemulihan yang cocok LANGSUNG ditandai terpakai (dihapus dari
 * daftar) — sekali pakai, tidak bisa dipakai ulang meski kebetulan
 * dicoba dua kali.
 */
final class VerifyTwoFactorCode
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function handle(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        if ($this->google2fa->verifyKey($user->two_factor_secret, trim($code))) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $normalized = Str::upper(trim($code));
        $index = array_search($normalized, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }
}
