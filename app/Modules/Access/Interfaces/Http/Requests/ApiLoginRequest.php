<?php

declare(strict_types=1);

namespace App\Modules\Access\Interfaces\Http\Requests;

/**
 * Login klien mobile/API (token Sanctum, DEC-02) — mewarisi seluruh
 * rate-limiting dari LoginRequest (5x per NRP+IP, 20x/900 detik per
 * NRP saja), TAPI tanpa captcha: paket captcha bergantung sesi, dan
 * klien mobile asli tidak pernah punya sesi web. Rate-limit ganda
 * yang sudah ada dianggap cukup sebagai pertahanan brute-force untuk
 * jalur ini (keputusan produk, bukan celah yang belum ditutup).
 */
final class ApiLoginRequest extends LoginRequest
{
    protected function requiresCaptcha(): bool
    {
        return false;
    }
}
