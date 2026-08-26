<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Access\Interfaces\Http\Requests\ApiLoginRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Sanctum sebagai identity provider tunggal (DEC-02) — jalur token
 * untuk klien non-SPA (mobile), memakai verifikasi kredensial yang
 * sama dengan sesi/cookie (lihat LoginTest).
 */
final class TokenAuthTest extends TestCase
{
    use DatabaseTransactions;

    private const NRP = '2015.07.0088';

    private const PASSWORD = 'RahasiaDemo!123';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(self::NRP.'|127.0.0.1');
    }

    public function test_kredensial_benar_menerbitkan_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['token', 'user' => ['id', 'nrp', 'nama', 'roles']]);
    }

    public function test_kredensial_salah_ditolak(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => 'salah',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('nrp');
    }

    public function test_token_dapat_mengakses_rute_terproteksi(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ])->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user');

        $response->assertOk();
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    /**
     * Login mobile TIDAK PERNAH mensyaratkan captcha, terlepas dari
     * environment — beda dari LoginRequest (web) yang hanya melewati
     * captcha di environment testing. Paket captcha bergantung sesi,
     * dan klien mobile asli tidak pernah punya sesi web (SEC-2026-09).
     * Diuji langsung lewat rules(), bukan lewat request env=testing
     * yang kebetulan sama-sama melewati captcha untuk alasan berbeda.
     */
    public function test_login_mobile_tidak_pernah_mensyaratkan_captcha(): void
    {
        $rules = (new ApiLoginRequest)->rules();

        $this->assertSame([], $rules['captcha_answer']);
    }

    public function test_logout_mencabut_token_yang_sedang_dipakai(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        // Klien uji Laravel memakai satu container sepanjang test, sehingga
        // guard 'sanctum' meng-cache pengguna hasil resolusi permintaan
        // pertama. Pada permintaan HTTP sungguhan hal ini tidak terjadi
        // (setiap permintaan = proses baru) — forgetGuards() menirukan itu.
        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    /** SEC-2026-08: sebelumnya tidak ada jejak audit sama sekali untuk penerbitan/pencabutan token. */
    public function test_penerbitan_dan_pencabutan_token_tercatat_di_audit_trail(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', self::NRP)->value('id');

        $token = $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ])->json('token');

        $this->assertNotNull(
            DB::table('aud_change_logs')
                ->where('auditable_type', 'user_account')->where('auditable_id', $employeeId)
                ->where('action', 'login_succeeded')->first()
        );

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/logout');

        $this->assertNotNull(
            DB::table('aud_change_logs')
                ->where('auditable_type', 'user_account')->where('auditable_id', $employeeId)
                ->where('action', 'logged_out')->first()
        );
    }
}
