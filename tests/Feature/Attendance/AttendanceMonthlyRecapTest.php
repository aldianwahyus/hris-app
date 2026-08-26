<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Domain\Uuid7;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rekap absensi bulanan (TOR Fase I, Absensi): agregat hadir/telat
 * per pegawai dalam lingkup OFFICE — "hari tanpa catatan" adalah
 * proksi (hari kerja Senin-Jumat dikurangi baris hadir+telat), BUKAN
 * status 'absen' sungguhan (tidak pernah ditulis kode manapun).
 */
final class AttendanceMonthlyRecapTest extends TestCase
{
    use DatabaseTransactions;

    public function test_agregasi_hadir_dan_telat_per_pegawai(): void
    {
        $this->insertAttendance('2021.05.0302', '2026-09-01', 'hadir');
        $this->insertAttendance('2021.05.0302', '2026-09-02', 'telat');
        $this->insertAttendance('2021.05.0302', '2026-09-03', 'hadir');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tampilan=bulanan&bulan=2026-09');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
    }

    public function test_lingkup_office_tetap_berlaku_pada_tampilan_bulanan(): void
    {
        $this->insertAttendance('2021.05.0302', '2026-09-01', 'hadir'); // KCP Gerung
        $this->insertAttendance('2019.09.0177', '2026-09-01', 'hadir'); // KC Selong — kantor berbeda

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tampilan=bulanan&bulan=2026-09');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertDontSeeText('Dewi Lestari');
    }

    public function test_bulan_tanpa_data_menampilkan_kosong(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tampilan=bulanan&bulan=2020-01');

        $response->assertOk();
        $response->assertSeeText('Belum ada data absensi');
    }

    private function insertAttendance(string $nrp, string $workDate, string $status): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        DB::table('att_attendance_records')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'check_in_at' => new DateTimeImmutable("{$workDate} 08:00:00"),
            'check_in_source' => 'fingerprint',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
