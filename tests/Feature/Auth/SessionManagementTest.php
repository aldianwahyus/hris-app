<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Manajemen Sesi Aktif — Fase 2 (evaluasi PM/client 2026-09-03).
 * Admin (system_admin) lihat/cabut sesi SIAPA PUN; pengguna biasa
 * HANYA sesi miliknya sendiri — ditegakkan di query (filter user_id),
 * bukan sekadar disembunyikan di UI.
 */
final class SessionManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_melihat_seluruh_sesi(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $other = $this->userWithNrp('2018.03.0142');
        $this->insertSession('sesi-uji-lain', $other->id);

        $response = $this->actingAs($sysAdmin)->get('/admin/sistem/sesi');

        $response->assertOk();
        $response->assertSeeText('Siti Rahmawati');
    }

    public function test_pengguna_biasa_tidak_bisa_akses_manajemen_sesi_bank_wide(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/admin/sistem/sesi');

        $response->assertForbidden();
    }

    public function test_system_admin_dapat_mencabut_sesi_siapa_pun(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $other = $this->userWithNrp('2018.03.0142');
        $this->insertSession('sesi-uji-cabut', $other->id);

        $response = $this->actingAs($sysAdmin)->post('/admin/sistem/sesi/sesi-uji-cabut/cabut');

        $response->assertRedirect(route('sysadmin.sessions.index'));
        $this->assertSame(0, DB::table('sessions')->where('id', 'sesi-uji-cabut')->count());
    }

    public function test_pengguna_hanya_melihat_sesinya_sendiri(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $lain = $this->userWithNrp('2017.11.0119');
        $this->insertSession('sesi-milik-lain', $lain->id);

        $response = $this->actingAs($siti)->get('/keamanan-saya');

        $response->assertOk();
        $response->assertDontSeeText('sesi-milik-lain');
    }

    public function test_pengguna_tidak_bisa_mencabut_sesi_milik_orang_lain(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $lain = $this->userWithNrp('2017.11.0119');
        $this->insertSession('sesi-milik-orang-lain', $lain->id);

        $response = $this->actingAs($siti)->post('/keamanan-saya/sesi-milik-orang-lain/cabut');

        $response->assertNotFound();
        $this->assertSame(1, DB::table('sessions')->where('id', 'sesi-milik-orang-lain')->count());
    }

    public function test_pengguna_dapat_mencabut_sesinya_sendiri_di_perangkat_lain(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $this->insertSession('sesi-siti-perangkat-lain', $siti->id);

        $response = $this->actingAs($siti)->post('/keamanan-saya/sesi-siti-perangkat-lain/cabut');

        $response->assertRedirect(route('security-settings.index'));
        $this->assertSame(0, DB::table('sessions')->where('id', 'sesi-siti-perangkat-lain')->count());
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '203.0.113.5',
            'user_agent' => 'Mozilla/5.0 (Uji Otomatis)',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->subMinutes(5)->timestamp,
        ]);
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
