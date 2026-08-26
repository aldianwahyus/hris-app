<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Sanctum sebagai identity provider tunggal (DEC-02) — masuk lewat
 * sesi/cookie memakai NRP + kata sandi, satu jalur verifikasi kredensial
 * yang sama dipakai penerbitan token API (lihat TokenAuthTest).
 */
final class LoginTest extends TestCase
{
    use DatabaseTransactions;

    private const NRP = '2015.07.0088';

    private const PASSWORD = 'RahasiaDemo!123';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(self::NRP.'|127.0.0.1');
        RateLimiter::clear('account|'.strtolower(self::NRP));
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/beranda')->assertRedirect('/masuk');
    }

    public function test_nrp_dan_kata_sandi_benar_berhasil_masuk(): void
    {
        $response = $this->post('/masuk', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(route('ess.dashboard'));

        $employeeId = DB::table('emp_employees')->where('nrp', self::NRP)->value('id');
        $this->assertAuthenticatedAs(User::query()->where('employee_id', $employeeId)->firstOrFail());
    }

    public function test_kata_sandi_salah_ditolak_dengan_pesan_generik(): void
    {
        $response = $this->from('/masuk')->post('/masuk', [
            'nrp' => self::NRP,
            'password' => 'salah-total',
        ]);

        $response->assertRedirect('/masuk');
        $response->assertSessionHasErrors('nrp');
        $this->assertGuest();
    }

    public function test_nrp_tidak_dikenal_ditolak_tanpa_membocorkan_keberadaan_akun(): void
    {
        $response = $this->post('/masuk', [
            'nrp' => '0000.00.0000',
            'password' => 'apa-saja',
        ]);

        $response->assertSessionHasErrors('nrp');
        $this->assertGuest();
    }

    public function test_percobaan_gagal_berulang_dibatasi(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', ['nrp' => self::NRP, 'password' => 'salah']);
        }

        $response = $this->post('/masuk', ['nrp' => self::NRP, 'password' => self::PASSWORD]);

        $response->assertSessionHasErrors('nrp');
        $this->assertGuest();
    }

    /** SEC-2026-08: sebelumnya tidak ada jejak audit sama sekali untuk peristiwa masuk. */
    public function test_masuk_berhasil_tercatat_di_audit_trail(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', self::NRP)->value('id');

        $this->post('/masuk', ['nrp' => self::NRP, 'password' => self::PASSWORD]);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'user_account')->where('auditable_id', $employeeId)
            ->where('action', 'login_succeeded')->first();
        $this->assertNotNull($audit);
    }

    public function test_masuk_gagal_pada_akun_dikenal_tercatat_di_audit_trail(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', self::NRP)->value('id');

        $this->post('/masuk', ['nrp' => self::NRP, 'password' => 'salah-total']);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'user_account')->where('auditable_id', $employeeId)
            ->where('action', 'login_failed')->first();
        $this->assertNotNull($audit);
    }

    public function test_keluar_tercatat_di_audit_trail(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', self::NRP)->value('id');
        $user = User::query()->where('employee_id', $employeeId)->firstOrFail();

        $this->actingAs($user)->post('/keluar');

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'user_account')->where('auditable_id', $employeeId)
            ->where('action', 'logged_out')->first();
        $this->assertNotNull($audit);
    }
}
