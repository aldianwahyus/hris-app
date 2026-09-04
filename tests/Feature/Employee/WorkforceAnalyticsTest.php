<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Core\Domain\Uuid7;
use App\Interfaces\Http\Support\WorkforceAnalytics;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Analitik Tenaga Kerja (Fase 2) — BERBASIS ATURAN, BUKAN machine
 * learning (lihat WorkforceAnalytics).
 */
final class WorkforceAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_approver_dapat_melihat_dasbor(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/analitik-tenaga-kerja');

        $response->assertOk();
        $response->assertSeeText('Analitik Tenaga Kerja');
        $response->assertSeeText('Turnover Rate');
        $response->assertSeeText('BUKAN prediksi machine learning');
    }

    public function test_pegawai_biasa_tidak_bisa_mengakses_dasbor(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/analitik-tenaga-kerja');

        $response->assertForbidden();
    }

    public function test_pegawai_dengan_masa_kerja_sesuai_dan_tanpa_cuti_terbaru_ditandai_risiko(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/analitik-tenaga-kerja');

        $response->assertOk();
        // Rina Marlina bergabung 2021-05-03 (~5 tahun) TANPA cuti disetujui pada fixture dasar.
        $response->assertSeeText('Rina Marlina');
    }

    public function test_pegawai_dengan_cuti_disetujui_baru_tidak_ditandai_risiko(): void
    {
        $rina = $this->employeeId('2021.05.0302');

        DB::table('leave_requests')->insert([
            'id' => (string) Uuid7::generate(),
            'request_number' => 'CT/TEST/0001',
            'employee_id' => $rina,
            'leave_type' => 'CT',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(8)->toDateString(),
            'total_days' => 3,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/analitik-tenaga-kerja');

        $response->assertOk();
        $response->assertDontSeeText('Rina Marlina');
    }

    public function test_pegawai_dengan_masa_kerja_terlalu_lama_tidak_ditandai_risiko(): void
    {
        // Nur Aisyah (NRP 2014.02.0061, bergabung 2014-02-03, ~12 tahun) di
        // luar jendela 1-7 tahun — dicek lewat data mentah, BUKAN scraping
        // HTML, karena nama peninjau yang login (Nur Aisyah sendiri) juga
        // muncul di header halaman, membuat assertDontSeeText seluruh
        // halaman keliru positif.
        $atRisk = app(WorkforceAnalytics::class)->atRiskEmployees();

        $this->assertFalse($atRisk->contains('nrp', '2014.02.0061'));
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
