<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Struktur Organisasi PER kantor/divisi — hierarki antar orang dibangun
 * dari emp_employees.supervisor_id (murni tampilan, TIDAK memengaruhi
 * wewenang persetujuan — lihat OrganizationChartController).
 */
final class OrganizationChartTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_melihat_pemilih_unit(): void
    {
        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get('/struktur-organisasi');

        $response->assertOk();
        $response->assertSeeText('Struktur organisasi');
        $response->assertSeeText('KC Mataram');
    }

    public function test_admin_hc_dapat_melihat_pemilih_unit(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/struktur-organisasi');

        $response->assertOk();
    }

    public function test_peran_lain_ditolak_dari_struktur_organisasi(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/struktur-organisasi');

        $response->assertForbidden();
    }

    public function test_bagan_kc_mataram_berjenjang_sesuai_atasan_langsung(): void
    {
        // Ahmad (Branch Manager) jadi atasan Siti — Siti harus muncul
        // BERSARANG di bawah Ahmad, bukan sejajar sebagai akar terpisah.
        $ahmadId = $this->employeeId('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142');
        DB::table('emp_employees')->where('id', $sitiId)->update(['supervisor_id' => $ahmadId]);

        $officeId = DB::table('emp_employees')->where('id', $ahmadId)->value('office_id');

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get("/struktur-organisasi/{$officeId}");

        $response->assertOk();
        $response->assertSeeTextInOrder(['Ahmad Fauzi', 'Siti Rahmawati']);
    }

    public function test_pegawai_tanpa_atasan_dalam_lingkup_jadi_akar_bagan(): void
    {
        $officeId = $this->employeeOfficeId('2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get("/struktur-organisasi/{$officeId}");

        $response->assertOk();
        $response->assertSeeText('Ahmad Fauzi');
    }

    public function test_siklus_atasan_tidak_membuat_lambat_tanpa_batas(): void
    {
        // A jadi atasan B, B jadi atasan A — pagar siklus di controller
        // harus mencegah rekursi tanpa batas, bukan malah error 500/timeout.
        $ahmadId = $this->employeeId('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142');
        DB::table('emp_employees')->where('id', $ahmadId)->update(['supervisor_id' => $sitiId]);
        DB::table('emp_employees')->where('id', $sitiId)->update(['supervisor_id' => $ahmadId]);

        $officeId = $this->employeeOfficeId('2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get("/struktur-organisasi/{$officeId}");

        $response->assertOk();
    }

    public function test_kantor_pusat_dipecah_per_divisi(): void
    {
        $nurAisyahId = $this->employeeId('2014.02.0061'); // Nur Aisyah — Kantor Pusat
        DB::table('emp_employees')->where('id', $nurAisyahId)->update(['division' => 'Divisi Operasional']);

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get('/struktur-organisasi');

        $response->assertOk();
        $response->assertSeeText('Divisi Operasional');
    }

    public function test_bagan_divisi_hanya_menampilkan_pegawai_divisi_itu(): void
    {
        $nurAisyahId = $this->employeeId('2014.02.0061');
        $officeId = $this->employeeOfficeId('2014.02.0061');
        DB::table('emp_employees')->where('id', $nurAisyahId)->update(['division' => 'Divisi Operasional']);

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))
            ->get("/struktur-organisasi/{$officeId}?divisi=Divisi+Operasional");

        $response->assertOk();
        $response->assertSeeText('Nur Aisyah');
    }

    public function test_unduh_pdf_bagan(): void
    {
        $officeId = $this->employeeOfficeId('2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get("/struktur-organisasi/{$officeId}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function employeeOfficeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('office_id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
