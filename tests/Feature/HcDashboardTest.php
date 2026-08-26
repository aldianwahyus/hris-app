<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Domain\Uuid7;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dashboard dasar (TOR Fase I) — lingkup BANK_WIDE untuk hr_approver
 * saja, agregat lintas kantor (BUKAN office-scoped seperti hr_admin).
 */
final class HcDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_approver_dapat_mengakses_dashboard_hc(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Dashboard HC');
    }

    public function test_peran_lain_ditolak_dari_dashboard_hc(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/dasbor-hc');

        $response->assertForbidden();
    }

    public function test_hr_admin_saja_tidak_bisa_mengakses_dashboard_hc(): void
    {
        // Rina Marlina hanya hr_admin (bukan hr_approver) — SoD (§6.3)
        // melarang satu orang memegang keduanya sekaligus.
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/dasbor-hc');

        $response->assertForbidden();
    }

    public function test_headcount_bersifat_bank_wide_bukan_office_scoped(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        // Dua kantor berbeda dari data contoh — membuktikan agregat
        // TIDAK dipotong ke satu kantor seperti AttendanceRecapController.
        $response->assertSeeText('KC Mataram');
        $response->assertSeeText('KCP Gerung');
    }

    public function test_pending_approval_lintas_modul_terhitung(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');

        DB::table('leave_requests')->insert([
            'id' => (string) Uuid7::generate(),
            'request_number' => 'CT/TEST/0001',
            'employee_id' => $employeeId,
            'leave_type' => 'CT',
            'start_date' => (new DateTimeImmutable('+10 days'))->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable('+11 days'))->format('Y-m-d'),
            'total_days' => 2,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Cuti & Izin');
    }

    public function test_gap_formasi_ditampilkan_saat_sudah_ditetapkan(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');
        $actual = DB::table('emp_employees')->where('office_id', $officeId)->count();
        DB::table('md_offices')->where('id', $officeId)->update(['authorized_headcount' => $actual + 5]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('GAP formasi per kantor');
        $response->assertSeeText("({$actual}/".($actual + 5).')');
    }

    public function test_kantor_tanpa_formasi_ditandai_belum_ditetapkan(): void
    {
        DB::table('md_offices')->whereNotNull('authorized_headcount')->update(['authorized_headcount' => null]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('belum ditetapkan');
    }

    public function test_headcount_per_grade_ditampilkan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Headcount per grade');
    }

    public function test_antrean_lama_muncul_di_bucket_kritis(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');

        DB::table('leave_requests')->insert([
            'id' => (string) Uuid7::generate(),
            'request_number' => 'CT/TEST/0002',
            'employee_id' => $employeeId,
            'leave_type' => 'CT',
            'start_date' => (new DateTimeImmutable('+30 days'))->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable('+31 days'))->format('Y-m-d'),
            'total_days' => 2,
            'status' => 'pending',
            'created_at' => (new DateTimeImmutable('-20 days')),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Umur antrean menunggu persetujuan');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
