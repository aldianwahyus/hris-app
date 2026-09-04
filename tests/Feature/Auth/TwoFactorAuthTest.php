<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * 2FA (TOTP) — Fase 2 (evaluasi PM/client 2026-09-03). hr_admin/
 * hr_approver/system_admin WAJIB (dipaksa setup di login pertama
 * setelah fitur ini aktif); pegawai biasa TIDAK dipaksa. Kata sandi
 * pengguna uji SELALU ditetapkan eksplisit di setiap tes (bukan
 * asumsi kata sandi demo bersama) — pola SAMA OffboardingTest.
 */
final class TwoFactorAuthTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'RahasiaUji2FA!1';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['2021.05.0302', '2014.02.0061', '2018.03.0142', 'SYSADMIN'] as $nrp) {
            RateLimiter::clear(strtolower($nrp).'|127.0.0.1');
            RateLimiter::clear('account|'.strtolower($nrp));
            DB::table('users')
                ->where('employee_id', $this->employeeId($nrp))
                ->update(['password' => Hash::make(self::PASSWORD)]);
        }
    }

    public function test_pegawai_biasa_tidak_dipaksa_2fa(): void
    {
        $response = $this->post('/masuk', ['nrp' => '2018.03.0142', 'password' => self::PASSWORD]);

        $response->assertRedirect(route('ess.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_hr_admin_dipaksa_setup_2fa_pada_login_pertama(): void
    {
        $response = $this->post('/masuk', ['nrp' => '2021.05.0302', 'password' => self::PASSWORD]);

        $response->assertRedirect(route('two-factor.setup'));
        $this->assertGuest();
    }

    public function test_tidak_bisa_akses_halaman_setup_tanpa_login_sebelumnya(): void
    {
        $response = $this->get('/2fa/setup');

        $response->assertForbidden();
    }

    public function test_setup_2fa_dengan_kode_benar_berhasil_dan_menampilkan_kode_pemulihan(): void
    {
        $this->post('/masuk', ['nrp' => '2021.05.0302', 'password' => self::PASSWORD]);

        $setupPage = $this->get('/2fa/setup');
        $setupPage->assertOk();
        $secret = session('2fa_setup_secret');
        $this->assertNotNull($secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $confirm = $this->post('/2fa/setup', ['code' => $code]);

        $confirm->assertOk();
        $confirm->assertSeeText('Simpan Kode Pemulihan Anda');
        $this->assertAuthenticated();

        $user = $this->userWithNrp('2021.05.0302');
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertCount(8, $user->two_factor_recovery_codes);
    }

    public function test_setup_2fa_dengan_kode_salah_gagal_dan_tidak_login(): void
    {
        $this->post('/masuk', ['nrp' => '2021.05.0302', 'password' => self::PASSWORD]);
        $this->get('/2fa/setup');

        $confirm = $this->post('/2fa/setup', ['code' => '000000']);

        $confirm->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNull($this->userWithNrp('2021.05.0302')->two_factor_confirmed_at);
    }

    public function test_login_dengan_2fa_sudah_aktif_diarahkan_ke_tantangan_lalu_berhasil_dengan_kode_benar(): void
    {
        $secret = $this->confirmTwoFactorFor('2014.02.0061');

        $login = $this->post('/masuk', ['nrp' => '2014.02.0061', 'password' => self::PASSWORD]);
        $login->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $verify = $this->post('/2fa/verifikasi', ['code' => $code]);

        // Nur Aisyah adalah hr_approver (BUKAN system_admin) — beranda pegawai (ESS) tetap relevan baginya.
        $verify->assertRedirect(route('ess.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_tantangan_2fa_dengan_kode_salah_gagal(): void
    {
        $this->confirmTwoFactorFor('2014.02.0061');
        $this->post('/masuk', ['nrp' => '2014.02.0061', 'password' => self::PASSWORD]);

        $verify = $this->post('/2fa/verifikasi', ['code' => '000000']);

        $verify->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_kode_pemulihan_bisa_dipakai_dan_hanya_sekali(): void
    {
        $this->confirmTwoFactorFor('2014.02.0061');
        $recoveryCode = $this->userWithNrp('2014.02.0061')->two_factor_recovery_codes[0];

        $this->post('/masuk', ['nrp' => '2014.02.0061', 'password' => self::PASSWORD]);
        $first = $this->post('/2fa/verifikasi', ['code' => $recoveryCode]);
        $first->assertRedirect();
        $this->assertAuthenticated();

        auth()->logout();
        $this->post('/masuk', ['nrp' => '2014.02.0061', 'password' => self::PASSWORD]);
        $second = $this->post('/2fa/verifikasi', ['code' => $recoveryCode]);

        $second->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_sysadmin_dapat_mereset_2fa_pengguna_lain(): void
    {
        $this->confirmTwoFactorFor('2021.05.0302');
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $targetUserId = $this->userWithNrp('2021.05.0302')->id;

        $response = $this->actingAs($sysAdmin)->post("/admin/sistem/pengguna/{$targetUserId}/reset-2fa");

        $response->assertRedirect(route('sysadmin.users.index'));
        $this->assertNull($this->userWithNrp('2021.05.0302')->two_factor_confirmed_at);
    }

    /** @return string secret TOTP yang baru dikonfirmasi */
    private function confirmTwoFactorFor(string $nrp): string
    {
        $this->post('/masuk', ['nrp' => $nrp, 'password' => self::PASSWORD]);

        $needsSetup = session()->has('2fa_pending_user_id') && ! DB::table('users')
            ->where('id', session('2fa_pending_user_id'))
            ->whereNotNull('two_factor_confirmed_at')
            ->exists();

        if ($needsSetup) {
            $this->get('/2fa/setup');
            $secret = session('2fa_setup_secret');
            $code = app(Google2FA::class)->getCurrentOtp($secret);
            $this->post('/2fa/setup', ['code' => $code]);
            auth()->logout();

            return $secret;
        }

        // Peran ini belum tentu wajib 2FA (mis. dipanggil untuk pegawai
        // biasa) — paksa aktifkan langsung di DB untuk kebutuhan tes.
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        DB::table('users')->where('employee_id', $this->employeeId($nrp))->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        return $secret;
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        return User::query()->where('employee_id', $this->employeeId($nrp))->firstOrFail();
    }
}
