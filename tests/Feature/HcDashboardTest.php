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

    /** system_admin SEKARANG juga diberi akses (migrasi 2026_09_18_000002) — sebelumnya hanya hr_approver. */
    public function test_system_admin_dapat_mengakses_dashboard_hc(): void
    {
        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Dashboard HC');
    }

    public function test_rincian_status_kepegawaian_dan_gender_ditampilkan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Pegawai Tetap');
        $response->assertSeeText('Pegawai Laki-laki');
        $response->assertSeeText('Pegawai Perempuan');
    }

    public function test_ulang_tahun_dalam_3_bulan_ke_depan_muncul_di_daftar(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $dalamSebulan = (new DateTimeImmutable('+1 month'));
        DB::table('emp_employees')->where('id', $employeeId)->update([
            'birth_date' => $dalamSebulan->modify('-30 years')->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Ulang tahun 3 bulan ke depan');
        $response->assertSeeText('Siti Rahmawati');
    }

    public function test_ulang_tahun_lebih_dari_3_bulan_lagi_tidak_muncul(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $enamBulanLagi = (new DateTimeImmutable('+6 months'));
        DB::table('emp_employees')->where('id', $employeeId)->update([
            'birth_date' => $enamBulanLagi->modify('-30 years')->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Tidak ada pegawai berulang tahun dalam periode ini.');
    }

    /** MPP = 6 bulan sebelum usia pensiun normal (RETIREMENT_NORMAL_AGE=56) — jadi usia 55 tahun 6 bulan. */
    public function test_pegawai_yang_akan_memasuki_mpp_muncul_di_daftar(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        // Lahir supaya genap 55 tahun 6 bulan dalam 1 bulan dari sekarang.
        $birthDate = (new DateTimeImmutable('+1 month'))->modify('-55 years')->modify('-6 months');
        DB::table('emp_employees')->where('id', $employeeId)->update(['birth_date' => $birthDate->format('Y-m-d')]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Akan memasuki MPP');
        $response->assertSeeText('Siti Rahmawati');
    }

    public function test_penghargaan_masa_bakti_15_tahun_muncul_di_daftar(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $joinDate = (new DateTimeImmutable('+1 month'))->modify('-15 years');
        DB::table('emp_employees')->where('id', $employeeId)->update(['join_date' => $joinDate->format('Y-m-d')]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Penghargaan Masa Bakti');
        $response->assertSeeText('15 Tahun');
        $response->assertSeeText('Siti Rahmawati');
    }

    public function test_jumlah_pegawai_dan_persebaran_jabatan_per_kc_kcp_ditampilkan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/dasbor-hc');

        $response->assertOk();
        $response->assertSeeText('Jumlah pegawai per KC & KCP');
        $response->assertSeeText('Persebaran jabatan per KC & KCP');
        $response->assertSeeText('KC Mataram');
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
