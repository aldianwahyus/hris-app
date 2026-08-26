<?php

declare(strict_types=1);

namespace Tests\Feature\Sppd;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengajuan SPPD (BPP/442/03/64/2026) — lingkup SELF, selalu atas nama
 * pegawai yang sedang masuk. Seluruh anggaran (Uang Makan/Saku, plafon
 * Hotel/Angkutan/Transportasi) DIHITUNG SISTEM dari jenjang jabatan +
 * kategori + lama hari — tidak ada kolom uang yang diterima dari form.
 */
final class SubmitSppdRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_mengajukan_jarak_pendek(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->post('/sppd/ajukan', [
            'trip_category' => 'jarak_pendek',
            'destination' => 'KCP Praya',
            'purpose' => 'Kunjungan kerja',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'radius_band' => '30_100',
        ]);

        $response->assertRedirect(route('ess.dashboard'));
        $response->assertSessionHas('sukses');

        $employeeId = $this->employeeId('2018.03.0142');
        $spd = DB::table('spd_requests')->where('employee_id', $employeeId)->first();

        $this->assertNotNull($spd);
        $this->assertSame(50_000_00, $spd->uang_makan_cents); // 1 hari x Rp50.000
        $this->assertSame(0, $spd->uang_saku_cents);
        $this->assertNull($spd->estimasi_hotel_cents);
        $this->assertNull($spd->jabatan_tier);
    }

    public function test_pegawai_dapat_mengajukan_jarak_jauh_dan_seluruh_anggaran_dihitung_sistem(): void
    {
        // Siti Rahmawati — Officer, tier team_leader_spv_officer_staff, 2 hari.
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->post('/sppd/ajukan', [
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Diklat',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11', // 2 hari
        ]);

        $response->assertRedirect(route('ess.dashboard'));
        $response->assertSessionHas('sukses');

        $spd = DB::table('spd_requests')->where('employee_id', $this->employeeId('2018.03.0142'))->first();

        $this->assertSame('team_leader_spv_officer_staff', $spd->jabatan_tier);
        $this->assertSame(500_000_00, $spd->uang_makan_cents); // 2 x 250.000
        $this->assertSame(900_000_00, $spd->uang_saku_cents);  // 2 x 450.000
        $this->assertSame(2_000_000_00, $spd->estimasi_hotel_cents); // 2 x 1.000.000
        $this->assertSame(500_000_00, $spd->estimasi_angkutan_setempat_cents); // 2 x 250.000
        $this->assertSame(750_000_00, $spd->estimasi_transportasi_tujuan_cents); // TIDAK dikali hari, satu kali PP
    }

    public function test_kolom_estimasi_yang_dikirim_lewat_form_diabaikan_sistem_tetap_menghitung_sendiri(): void
    {
        // Percobaan menyisipkan nilai uang lewat body request (mis. lewat
        // devtools) — request tidak lagi memiliki aturan validasi untuk
        // kolom ini sama sekali, jadi nilai ini TIDAK BOLEH sampai ke DB.
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->post('/sppd/ajukan', [
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Diklat',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'estimasi_hotel' => 99_999_999,
            'estimasi_angkutan_setempat' => 99_999_999,
            'estimasi_transportasi_tujuan' => 99_999_999,
        ]);

        $response->assertSessionHas('sukses');

        $spd = DB::table('spd_requests')->where('employee_id', $this->employeeId('2018.03.0142'))->first();

        $this->assertSame(1_000_000_00, $spd->estimasi_hotel_cents); // 1 hari x 1.000.000, bukan nilai kiriman
    }

    public function test_formulir_tidak_memiliki_input_uang_yang_dapat_diisi_manual(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/sppd/ajukan');

        $response->assertOk();
        $response->assertDontSee('name="estimasi_hotel"', false);
        $response->assertDontSee('name="estimasi_angkutan_setempat"', false);
        $response->assertDontSee('name="estimasi_transportasi_tujuan"', false);
        $response->assertDontSee('name="uang_makan"', false);
        $response->assertDontSee('name="uang_saku"', false);
    }

    public function test_pratinjau_get_menampilkan_perkiraan_anggaran_sesuai_jenjang_dan_kategori(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/sppd/ajukan?'.http_build_query([
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
        ]));

        $response->assertOk();
        $response->assertSeeText('Perkiraan Anggaran');
        // 2 hari x Rp250.000, x Rp450.000, plafon hotel 2 x Rp1.000.000, dst.
        $response->assertSeeText('Rp500.000');
        $response->assertSeeText('Rp900.000');
        $response->assertSeeText('Rp2.000.000');
        $response->assertSeeText('Rp500.000');
        $response->assertSeeText('Rp750.000');
    }

    public function test_jabatan_tanpa_pemetaan_tier_ditolak(): void
    {
        // Administrator Sistem — posisi SYS_ADMIN, sengaja TIDAK dipetakan
        // ke jenjang tarif SPPD (bukan pegawai SDM sungguhan).
        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->post('/sppd/ajukan', [
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
        ]);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('belum dipetakan', session('gagal'));
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
