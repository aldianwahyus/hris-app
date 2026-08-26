<?php

declare(strict_types=1);

namespace App\Modules\Access\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    // Cadangan terpisah dari throttleKey() (NRP+IP): penyerang yang
    // memutar-mutar alamat IP (botnet/proxy/NAT carrier) mendapat
    // jatah 5 percobaan BARU pada setiap pergantian IP terhadap NRP
    // yang SAMA — kunci ini menghitung PER NRP SAJA, lepas dari IP,
    // sebagai jaring pengaman kedua terhadap credential stuffing
    // terdistribusi. Ambang lebih tinggi (bukan 5) karena kunci ini
    // juga terkena pengguna sah yang berpindah jaringan (WFH/kantor).
    private const MAX_ATTEMPTS_PER_ACCOUNT = 20;

    private const ACCOUNT_DECAY_SECONDS = 900; // 15 menit

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nrp' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
            // Divalidasi SEBELUM ensureIsNotRateLimited() dipanggil di
            // LoginController (aturan FormRequest jalan lebih dulu) — captcha
            // salah tidak memakan jatah percobaan rate-limit NRP+IP/akun.
            // Dilewati pada environment testing — gambar captcha tidak
            // pernah dirender di sana, tidak ada jawaban sah untuk diuji,
            // dan lingkungan ini tidak pernah menghadapi trafik sungguhan.
            'captcha_answer' => $this->requiresCaptcha() ? ['required', 'string', 'captcha'] : [],
        ];
    }

    protected function requiresCaptcha(): bool
    {
        return ! $this->isTesting();
    }

    private function isTesting(): bool
    {
        return app()->environment('testing');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'captcha_answer.required' => 'Kode keamanan wajib diisi.',
            'captcha_answer.captcha' => 'Kode keamanan tidak sesuai.',
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'nrp' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (RateLimiter::tooManyAttempts($this->accountThrottleKey(), self::MAX_ATTEMPTS_PER_ACCOUNT)) {
            $seconds = RateLimiter::availableIn($this->accountThrottleKey());

            throw ValidationException::withMessages([
                'nrp' => "Terlalu banyak percobaan pada akun ini dari berbagai alamat. Coba lagi dalam {$seconds} detik.",
            ]);
        }
    }

    public function recordFailedAttempt(): void
    {
        RateLimiter::hit($this->throttleKey());
        RateLimiter::hit($this->accountThrottleKey(), self::ACCOUNT_DECAY_SECONDS);
    }

    public function clearAttempts(): void
    {
        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountThrottleKey());
    }

    private function throttleKey(): string
    {
        return Str::lower((string) $this->string('nrp')).'|'.$this->ip();
    }

    private function accountThrottleKey(): string
    {
        return 'account|'.Str::lower((string) $this->string('nrp'));
    }
}
