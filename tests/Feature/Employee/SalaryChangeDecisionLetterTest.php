<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SK Perubahan Gaji — layar KHUSUS (bukan form massal SK generik):
 * gaji SAAT INI ditampilkan per pegawai, perubahan diisi PER PEGAWAI
 * (boleh beda-beda, boleh dikosongkan kalau tidak berubah), plus jalur
 * impor massal CSV dengan template yang disediakan sistem.
 */
final class SalaryChangeDecisionLetterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_halaman_buat_menampilkan_gaji_saat_ini_pegawai(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');

        $response = $this->actingAs($rina)->get('/sk/perubahan-gaji/buat');

        $response->assertOk();
        $response->assertSeeText('Golongan Saat Ini');
        $response->assertSeeText('Tunj. Jabatan Saat Ini');
    }

    public function test_dua_pegawai_disubmit_sekaligus_dengan_nilai_berbeda(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $employeeIdA = $this->employeeId('2018.03.0142');
        $employeeIdB = $this->employeeId('2017.11.0119');

        $response = $this->actingAs($sysadmin)->post('/sk/perubahan-gaji', [
            'sk_number' => 'SK-GAJI/001/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Kenaikan berkala.',
            'changes' => [
                $employeeIdA => ['tunjangan_jabatan' => 1_000_000],
                $employeeIdB => ['person_grade' => 7, 'tunjangan_penyesuaian' => 300_000],
            ],
        ]);

        $response->assertRedirect(route('sk.index'));
        $this->assertStringContainsString('2 dari 2 pegawai', session('sukses'));

        $skA = DB::table('emp_decision_letters')->where('employee_id', $employeeIdA)->where('sk_number', 'SK-GAJI/001/2026')->first();
        $pcrA = DB::table('emp_profile_change_requests')->where('id', $skA->profile_change_request_id)->first();
        $proposedA = json_decode($pcrA->proposed_changes, true);
        $this->assertSame(100_000_000, $proposedA['tunjangan_jabatan_cents']);
        $this->assertArrayNotHasKey('person_grade', $proposedA);

        $skB = DB::table('emp_decision_letters')->where('employee_id', $employeeIdB)->where('sk_number', 'SK-GAJI/001/2026')->first();
        $pcrB = DB::table('emp_profile_change_requests')->where('id', $skB->profile_change_request_id)->first();
        $proposedB = json_decode($pcrB->proposed_changes, true);
        $this->assertSame(7, $proposedB['person_grade']);
        $this->assertSame(30_000_000, $proposedB['tunjangan_penyesuaian_cents']);
        $this->assertArrayNotHasKey('tunjangan_jabatan_cents', $proposedB);
    }

    public function test_baris_pegawai_yang_dikosongkan_tidak_membuat_sk(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $employeeIdA = $this->employeeId('2018.03.0142');
        $employeeIdB = $this->employeeId('2017.11.0119');

        $this->actingAs($sysadmin)->post('/sk/perubahan-gaji', [
            'sk_number' => 'SK-GAJI/002/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Hanya satu pegawai berubah.',
            'changes' => [
                $employeeIdA => ['tunjangan_jabatan' => 500_000],
                $employeeIdB => ['person_grade' => null, 'salary_step' => null, 'tunjangan_jabatan' => null, 'tunjangan_penyesuaian' => null],
            ],
        ]);

        $this->assertSame(1, DB::table('emp_decision_letters')->where('sk_number', 'SK-GAJI/002/2026')->count());
        $this->assertSame(
            0,
            DB::table('emp_decision_letters')->where('sk_number', 'SK-GAJI/002/2026')->where('employee_id', $employeeIdB)->count()
        );
    }

    public function test_tidak_ada_satu_pun_baris_diisi_ditolak(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $employeeIdA = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($sysadmin)->post('/sk/perubahan-gaji', [
            'sk_number' => 'SK-GAJI/003/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Tidak ada yang diisi.',
            'changes' => [
                $employeeIdA => ['person_grade' => null, 'salary_step' => null, 'tunjangan_jabatan' => null, 'tunjangan_penyesuaian' => null],
            ],
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('Tidak ada perubahan gaji', session('gagal'));
        $this->assertSame(0, DB::table('emp_decision_letters')->where('sk_number', 'SK-GAJI/003/2026')->count());
    }

    public function test_hr_admin_ditolak_kalau_ada_pegawai_di_luar_lingkup_dalam_payload(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung
        $sitiId = $this->employeeId('2018.03.0142'); // KC Mataram — di luar lingkup

        $response = $this->actingAs($rina)->post('/sk/perubahan-gaji', [
            'sk_number' => 'SK-GAJI/004/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Uji manipulasi payload.',
            'changes' => [
                $sitiId => ['tunjangan_jabatan' => 100_000],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(0, DB::table('emp_decision_letters')->where('sk_number', 'SK-GAJI/004/2026')->count());
    }

    public function test_template_csv_berisi_gaji_saat_ini_dalam_lingkup_actor(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung

        $response = $this->actingAs($rina)->get('/sk/perubahan-gaji/template');

        $response->assertOk();

        $csv = $response->getContent();
        $this->assertStringContainsString('nrp,nama,golongan_saat_ini,step_saat_ini,tunjangan_jabatan_saat_ini,tunjangan_penyesuaian_saat_ini,golongan_baru,step_baru,tunjangan_jabatan_baru,tunjangan_penyesuaian_baru', $csv);
        $this->assertStringContainsString('2021.05.0302', $csv);
        // KC Mataram di luar lingkup hr_admin KCP Gerung — tidak boleh muncul di template.
        $this->assertStringNotContainsString('2018.03.0142', $csv);
    }

    public function test_impor_csv_membuat_sk_untuk_baris_terisi_dan_melewati_yang_kosong(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $nrpBerubah = '2018.03.0142';
        $nrpTanpaPerubahan = '2017.11.0119';

        $csv = "nrp,nama,golongan_saat_ini,step_saat_ini,tunjangan_jabatan_saat_ini,tunjangan_penyesuaian_saat_ini,golongan_baru,step_baru,tunjangan_jabatan_baru,tunjangan_penyesuaian_baru\n"
            ."{$nrpBerubah},Uji,3,1,0,0,4,,750000,\n"
            ."{$nrpTanpaPerubahan},Uji,3,1,0,0,,,,\n"
            ."NRP.TIDAK.ADA,Uji,,,,,,,5,\n";

        $path = tempnam(sys_get_temp_dir(), 'sk_gaji_');
        file_put_contents($path, $csv);

        $response = $this->actingAs($sysadmin)->post('/sk/perubahan-gaji/impor', [
            'sk_number' => 'SK-GAJI/005/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Impor massal.',
            'berkas' => new UploadedFile($path, 'template.csv', 'text/csv', null, true),
        ]);

        $response->assertRedirect(route('sk.index'));
        $this->assertStringContainsString('1 pegawai diproses', session('sukses'));
        $this->assertStringContainsString('1 tanpa perubahan', session('sukses'));
        $this->assertStringContainsString('1 baris gagal', session('sukses'));

        $employeeIdBerubah = $this->employeeId($nrpBerubah);
        $sk = DB::table('emp_decision_letters')->where('employee_id', $employeeIdBerubah)->where('sk_number', 'SK-GAJI/005/2026')->first();
        $this->assertNotNull($sk);

        $pcr = DB::table('emp_profile_change_requests')->where('id', $sk->profile_change_request_id)->first();
        $proposed = json_decode($pcr->proposed_changes, true);
        $this->assertSame(4, $proposed['person_grade']);
        $this->assertSame(75_000_000, $proposed['tunjangan_jabatan_cents']);

        $employeeIdTanpaPerubahan = $this->employeeId($nrpTanpaPerubahan);
        $this->assertSame(
            0,
            DB::table('emp_decision_letters')->where('employee_id', $employeeIdTanpaPerubahan)->where('sk_number', 'SK-GAJI/005/2026')->count()
        );

        unlink($path);
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
