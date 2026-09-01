<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Domain\Uuid7;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rekap Absensi untuk Admin SDM — lingkup OFFICE (bukan OFFICE_TREE),
 * sama seperti Data Pegawai.
 */
final class AttendanceRecapScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_hanya_melihat_rekap_kantornya_sendiri(): void
    {
        $this->insertAttendance('2021.05.0302', '07:50:00'); // Rina Marlina — KCP Gerung
        $this->insertAttendance('2019.09.0177', '07:50:00'); // Dewi Lestari — KC Selong (induk KCP Gerung)

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/pegawai/absensi');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertDontSeeText('Dewi Lestari');
    }

    public function test_peran_lain_ditolak_dari_rekap_absensi(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/pegawai/absensi');

        $response->assertForbidden();
    }

    /** hr_approver — BANK_WIDE, melihat lintas kantor (bukan lingkup OFFICE seperti hr_admin). */
    public function test_hr_approver_melihat_rekap_seluruh_bank(): void
    {
        $this->insertAttendance('2021.05.0302', '07:50:00'); // Rina Marlina — KCP Gerung
        $this->insertAttendance('2019.09.0177', '07:50:00'); // Dewi Lestari — KC Selong

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/pegawai/absensi'); // Division Head, Kantor Pusat

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertSeeText('Dewi Lestari');
        $response->assertSeeText('Seluruh Bank');
    }

    public function test_hr_approver_dapat_menyaring_berdasarkan_tipe_kantor(): void
    {
        $this->insertAttendance('2021.05.0302', '07:50:00'); // Rina Marlina — KCP Gerung (sub_branch)
        $this->insertAttendance('2019.09.0177', '07:50:00'); // Dewi Lestari — KC Selong (branch)

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->get('/pegawai/absensi?tipe_kantor=sub_branch');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertDontSeeText('Dewi Lestari');
    }

    public function test_rekap_bulanan_hari_kerja_kecualikan_hari_libur_nasional(): void
    {
        // September 2026 punya 22 hari kerja mentah (Senin-Jumat) — 1
        // hari libur nasional yang di-seed di dalamnya harus mengurangi
        // itu jadi 21, bukan tetap 22 seperti perhitungan lama.
        DB::table('cfg_national_holidays')->insert([
            'id' => (string) Uuid7::generate(),
            'holiday_date' => '2026-09-03',
            'name' => 'Uji Libur Nasional',
            'is_national' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tampilan=bulanan&bulan=2026-09');

        $response->assertOk();
        $response->assertSeeText('21 hari kerja pada bulan ini');
    }

    /** Harian sekarang di-scope ke SATU tanggal (default hari ini), bukan lagi "200 baris terbaru" apa adanya. */
    public function test_rekap_harian_hanya_menampilkan_tanggal_yang_dipilih(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2021.05.0302')->value('id');

        DB::table('att_attendance_records')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'work_date' => '2026-09-10',
            'check_in_at' => new DateTimeImmutable('2026-09-10 07:50:00'),
            'check_in_source' => 'fingerprint',
            'status' => 'hadir',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $lain = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tanggal=2026-09-11');
        $lain->assertOk();
        $lain->assertSeeText('Belum ada data absensi pada tanggal ini.');

        $benar = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tanggal=2026-09-10');
        $benar->assertOk();
        $benar->assertSeeText('Rina Marlina');
    }

    public function test_rekap_mingguan_menghitung_hari_kerja_senin_sampai_minggu(): void
    {
        // 2026-09-07 (Senin) s.d. 2026-09-13 (Minggu) — ISO week 2026-W37,
        // 5 hari kerja mentah, tanpa libur nasional dalam rentang ini.
        $this->insertAttendanceOn('2021.05.0302', '2026-09-08', '07:50:00');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tampilan=mingguan&minggu=2026-W37');

        $response->assertOk();
        $response->assertSeeText('5 hari kerja pada minggu ini');
        $response->assertSeeText('Rina Marlina');
    }

    public function test_rekap_tahunan_menghitung_hari_kerja_setahun_penuh(): void
    {
        $this->insertAttendanceOn('2021.05.0302', '2026-03-02', '07:50:00');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tampilan=tahunan&tahun=2026');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertDontSeeText('Belum ada data absensi pada periode ini.');
    }

    public function test_rekap_rentang_bebas_menyaring_sesuai_dari_sampai(): void
    {
        $this->insertAttendanceOn('2021.05.0302', '2026-05-15', '07:50:00');

        // assertDontSeeText('Rina Marlina') TIDAK BISA dipakai di sini — dia
        // aktor yang login, namanya SELALU tampil di sidebar apa pun isi
        // tabel rekapnya. Cek pesan kosong sebagai gantinya.
        $luar = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tampilan=rentang&dari=2026-06-01&sampai=2026-06-30');
        $luar->assertOk();
        $luar->assertSeeText('Belum ada data absensi pada periode ini.');

        $dalam = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/absensi?tampilan=rentang&dari=2026-05-01&sampai=2026-05-31');
        $dalam->assertOk();
        $dalam->assertSeeText('Rina Marlina');
    }

    private function insertAttendanceOn(string $nrp, string $workDate, string $jamMasuk): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        DB::table('att_attendance_records')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'check_in_at' => new DateTimeImmutable("{$workDate} {$jamMasuk}"),
            'check_in_source' => 'fingerprint',
            'status' => 'hadir',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    private function insertAttendance(string $nrp, string $jamMasuk): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        DB::table('att_attendance_records')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'work_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'check_in_at' => new DateTimeImmutable("today {$jamMasuk}"),
            'check_in_source' => 'fingerprint',
            'status' => 'hadir',
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
