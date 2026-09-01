<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dashboard Cabang — BARU, lingkup OFFICE untuk hr_admin (belum pernah
 * ada dashboard untuk peran ini sebelumnya). Pola scope SAMA seperti
 * AttendanceRecapController::resolveScope() (hr_admin = office milik
 * aktor sendiri), TAPI ini file terpisah dari HcDashboardController
 * yang bank-wide.
 */
final class BranchDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_dapat_mengakses_dashboard_cabang_sendiri(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/pegawai/dasbor');

        $response->assertOk();
        $response->assertSeeText('Dashboard Cabang');
        $response->assertSeeText('Rina Marlina'); // dirinya sendiri, satu-satunya pegawai KCP Gerung di data contoh
    }

    public function test_hanya_menampilkan_pegawai_kantor_sendiri(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/pegawai/dasbor'); // KCP Gerung

        $response->assertOk();
        $response->assertDontSeeText('Dewi Lestari'); // KC Selong, kantor lain
    }

    public function test_hr_approver_tidak_bisa_mengakses_dashboard_cabang(): void
    {
        // hr_approver BANK_WIDE, bukan office-scoped — pakai Dashboard HC, bukan ini.
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/pegawai/dasbor');

        $response->assertForbidden();
    }

    public function test_peran_lain_ditolak_dari_dashboard_cabang(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/pegawai/dasbor');

        $response->assertForbidden();
    }

    public function test_rincian_status_kepegawaian_dan_gender_ditampilkan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/pegawai/dasbor');

        $response->assertOk();
        $response->assertSeeText('Total pegawai');
        $response->assertSeeText('Pegawai Perempuan');
    }

    public function test_ulang_tahun_kantor_sendiri_dalam_3_bulan_ke_depan_muncul(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2021.05.0302')->value('id');
        $dalamSebulan = (new DateTimeImmutable('+1 month'));
        DB::table('emp_employees')->where('id', $employeeId)->update([
            'birth_date' => $dalamSebulan->modify('-28 years')->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/pegawai/dasbor');

        $response->assertOk();
        $response->assertSeeText('Ulang tahun 3 bulan ke depan');
        $response->assertSeeText('Rina Marlina');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
