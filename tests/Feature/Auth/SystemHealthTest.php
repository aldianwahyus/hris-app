<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Interfaces\Http\Support\SystemHealthCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Dashboard Kesehatan Sistem — Fase 2 (evaluasi PM/client 2026-09-03). */
final class SystemHealthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_melihat_dashboard_kesehatan(): void
    {
        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get('/admin/sistem/kesehatan-sistem');

        $response->assertOk();
        $response->assertSeeText('Basis Data');
        $response->assertSeeText('Redis');
        $response->assertSeeText('Antrean');
    }

    public function test_pengguna_biasa_tidak_bisa_akses_dashboard_kesehatan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/admin/sistem/kesehatan-sistem');

        $response->assertForbidden();
    }

    public function test_semua_komponen_sehat_di_lingkungan_uji(): void
    {
        $checks = app(SystemHealthCheck::class)->run();

        foreach ($checks as $component => $result) {
            $this->assertTrue($result['ok'], "Komponen \"{$component}\" seharusnya sehat di lingkungan uji: {$result['detail']}");
        }
    }

    /**
     * SystemHealthCheck (seperti seluruh kelas lain di basis kode ini)
     * SENGAJA `final` — TIDAK di-mock (Mockery tidak bisa mock kelas
     * final, dan basis kode ini memang tidak punya preseden mocking
     * di mana pun). Kegagalan Redis dipicu SUNGGUHAN lewat config host
     * tidak valid, pola "uji lewat perilaku nyata" yang konsisten
     * dipakai seluruh test suite ini.
     */
    public function test_command_health_check_mencatat_warning_saat_komponen_gagal(): void
    {
        config(['database.redis.default.host' => 'host-tidak-valid-untuk-uji']);

        Log::shouldReceive('warning')->once()->withArgs(fn (string $message) => str_starts_with($message, 'Pemeriksaan kesehatan sistem gagal: redis'));

        $this->artisan('health:check')->assertExitCode(1);
    }

    public function test_command_health_check_sukses_saat_semua_komponen_sehat(): void
    {
        Log::shouldReceive('warning')->never();

        $this->artisan('health:check')->assertExitCode(0);
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
