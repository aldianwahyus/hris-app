<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sidebar hanya menampilkan menu yang benar-benar berwenang dipakai
 * peran yang sedang masuk — cerminan langsung ARCH-001 §6.2/§6.3 pada
 * antarmuka, bukan sekadar sembunyi-tampil kosmetik.
 */
final class SidebarMenuTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_biasa_hanya_melihat_menu_pengajuan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/beranda');

        $response->assertOk();
        $response->assertSeeText('Ajukan Cuti');
        $response->assertSeeText('Ajukan Lembur');
        $response->assertSeeText('Absensi');
        $response->assertSeeText('Slip Gaji');
        $response->assertSeeText('Ajukan SPPD');
        $response->assertSeeText('Ajukan Tukar Shift');
        $response->assertDontSeeText('Antrean Lembur');
        $response->assertDontSeeText('Antrean Cuti');
        $response->assertDontSeeText('Manajemen Pengguna');
        $response->assertDontSeeText('Data Pegawai');
        $response->assertDontSeeText('Rekap Absensi');
        $response->assertDontSeeText('Log Audit');
    }

    /**
     * Ahmad Fauzi memegang DUA peran persetujuan sekaligus (atasan_langsung
     * dari data contoh awal + pimpinan_kantor yang dipinjamkan untuk KC
     * Mataram, lihat 2026_08_19_000001_seed_pimpinan_kantor_role — ganti
     * dari direktur_pembina/DEC-92 versi awal sejak lembur jadi 2 tahap)
     * — sidebar harus menampilkan KEDUA antrean, bukan menyembunyikan
     * salah satunya.
     */
    public function test_akun_dengan_dua_peran_persetujuan_melihat_kedua_antrean(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/beranda');

        $response->assertOk();
        $response->assertSeeText('Antrean Lembur');   // atasan_langsung DAN pimpinan_kantor
        $response->assertSeeText('Antrean Cuti');     // atasan_langsung
        $response->assertSeeText('Antrean SPPD');     // atasan_langsung
        $response->assertSeeText('Antrean Tukar Shift'); // atasan_langsung, 1 tahap
        $response->assertDontSeeText('Manajemen Pengguna');
    }

    public function test_auditor_melihat_kedua_antrean_dan_log_audit_tanpa_menu_pengajuan_terkait_peran_lain(): void
    {
        $response = $this->actingAs($this->userWithNrp('2020.01.0231'))->get('/beranda');

        $response->assertOk();
        $response->assertSeeText('Antrean Lembur');
        $response->assertSeeText('Antrean Cuti');
        $response->assertSeeText('Log Audit');
        $response->assertDontSeeText('Manajemen Pengguna');
        $response->assertDontSeeText('Data Pegawai');
    }

    public function test_admin_sistem_hanya_melihat_manajemen_pengguna_tanpa_menu_sdm(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysAdmin)->get(route('sysadmin.users.index'));

        $response->assertOk();
        $response->assertSeeText('Manajemen Pengguna');
        $response->assertSeeText('Konfigurasi Parameter');
        $response->assertSeeText('Skala Imbalan Kerja');
        $response->assertSeeText('Tarif SPPD');
        $response->assertDontSeeText('Ajukan Cuti');
        $response->assertDontSeeText('Ajukan Lembur');
        $response->assertDontSeeText('Antrean Lembur');
        $response->assertDontSeeText('Antrean Cuti');
    }

    public function test_hr_admin_melihat_data_pegawai(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/beranda');

        $response->assertOk();
        $response->assertSeeText('Data Pegawai');
        $response->assertSeeText('Rekap Absensi');
        $response->assertDontSeeText('Manajemen Pengguna');
        // SEC-2026-08: admin cabang (hr_admin) TIDAK PERNAH boleh
        // generate/menyetujui payroll — hanya Human Capital (hr_approver).
        // Wewenang BARU yang sempit (input potongan pada draf yang SUDAH
        // dibuat HC) TIDAK melanggar itu — labelnya sengaja beda ("Potongan
        // Gaji", bukan "Payroll") supaya cakupannya jelas dari sidebar.
        $response->assertSeeText('Potongan Gaji');
        $response->assertDontSeeText('Generate Semua Kantor');
        $response->assertDontSeeText('Persetujuan Payroll');
    }

    public function test_hr_approver_melihat_persetujuan_payroll(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/beranda');

        $response->assertOk();
        $response->assertSeeText('Persetujuan Payroll');
    }

    /**
     * Admin HC (hr_approver) SEKARANG juga bisa kelola peran pengguna
     * (Bagian B) — TETAP tidak melihat menu khusus IT (reset kata
     * sandi tidak punya menu tersendiri, tapi Konfigurasi
     * Parameter/Impor Absensi Mesin dkk tetap system_admin saja).
     */
    public function test_admin_hc_melihat_manajemen_pengguna_tanpa_menu_khusus_it(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/beranda');

        $response->assertOk();
        $response->assertSeeText('Manajemen Pengguna');
        $response->assertSeeText('Peta Peran');
        $response->assertSeeText('Kalender Hari Libur');
        $response->assertSeeText('Struktur Organisasi');
        $response->assertSeeText('Pola Shift');
        $response->assertSeeText('Penugasan Shift');
        $response->assertSeeText('Formasi Kantor');
        $response->assertDontSeeText('Konfigurasi Parameter');
        $response->assertDontSeeText('Impor Absensi Mesin');
        $response->assertDontSeeText('Titik Ordinat Kantor');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
