<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Impor massal usulan pegawai baru dari CSV — jalur maker SAMA dengan
 * SystemAdminEmployeeController::store() (satu baris CSV = satu usulan
 * pending), maker-checker (§6.3) TIDAK dilewati: emp_employees TIDAK
 * PERNAH ditulis langsung dari impor.
 */
final class EmployeeImportTest extends TestCase
{
    use DatabaseTransactions;

    private const HEADER = "nrp,nama,tanggal_lahir,jenis_kelamin,email,tanggal_masuk,status_kepegawaian,kode_kantor,kode_jabatan,golongan,job_grade\n";

    public function test_impor_valid_membuat_usulan_pending_bukan_pegawai_langsung(): void
    {
        $csv = self::HEADER."2099.02.0001,Uji Impor Satu,1995-01-01,L,,2026-01-01,trainee,KC-MTR,OFC,5,10\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $response->assertRedirect(route('sysadmin.employees.import.index'));
        $response->assertSessionHas('sukses');

        $this->assertSame(0, DB::table('emp_employees')->where('nrp', '2099.02.0001')->count());

        $pending = DB::table('emp_new_employee_requests')->where('status', 'pending')
            ->get(['proposed_data'])
            ->first(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.02.0001');

        $this->assertNotNull($pending);
        $proposed = json_decode($pending->proposed_data, true);
        $this->assertSame('Uji Impor Satu', $proposed['full_name']);
        $this->assertSame(DB::table('md_offices')->where('code', 'KC-MTR')->value('id'), $proposed['office_id']);
        $this->assertSame(DB::table('md_positions')->where('code', 'OFC')->value('id'), $proposed['position_id']);
    }

    public function test_hr_approver_tetap_wajib_menyetujui_satu_per_satu_setelah_impor(): void
    {
        $csv = self::HEADER."2099.02.0002,Uji Impor Dua,,,,2026-01-01,trainee,KC-MTR,OFC,,\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $requestId = DB::table('emp_new_employee_requests')->where('status', 'pending')
            ->get(['id', 'proposed_data'])
            ->first(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.02.0002')
            ->id;

        $hrApprover = $this->userWithNrp('2014.02.0061');
        $response = $this->actingAs($hrApprover)->post("/persetujuan/pegawai-baru/{$requestId}/setujui");

        $response->assertRedirect(route('admin.employee-approval-queue'));
        $this->assertNotNull(DB::table('emp_employees')->where('nrp', '2099.02.0002')->first());
    }

    public function test_nrp_duplikat_dilewati_dengan_alasan(): void
    {
        $csv = self::HEADER."2018.03.0142,Sudah Ada,,,,2026-01-01,trainee,KC-MTR,OFC,,\n"; // NRP Siti, sudah ada
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('dilewati', session('gagal'));
    }

    public function test_kode_kantor_tidak_dikenal_dilewati(): void
    {
        $csv = self::HEADER."2099.02.0003,Uji Kantor Salah,,,,2026-01-01,tetap,TIDAK-ADA,OFC,,\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('emp_new_employee_requests')
            ->get(['proposed_data'])
            ->filter(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.02.0003')
            ->count());
    }

    public function test_kode_kantor_nonaktif_dilewati(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');
        DB::table('md_offices')->where('id', $officeId)->update(['is_active' => false]);

        $csv = self::HEADER."2099.02.0004,Uji Kantor Nonaktif,,,,2026-01-01,tetap,KC-MTR,OFC,,\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('emp_employees')->where('nrp', '2099.02.0004')->count());
    }

    public function test_status_kepegawaian_tidak_valid_dilewati(): void
    {
        $csv = self::HEADER."2099.02.0005,Uji Status Salah,,,,2026-01-01,bukan_status,KC-MTR,OFC,,\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('emp_new_employee_requests')
            ->get(['proposed_data'])
            ->filter(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.02.0005')
            ->count());
    }

    public function test_header_tanpa_kolom_wajib_ditolak(): void
    {
        $csv = "nrp,nama\n2099.02.0006,Uji Header Salah\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
    }

    /**
     * Regresi: template CSV yang diunduh dulu dibaca Excel sebagai SATU
     * kolom (baris header dobel utuh masuk ke kolom A) karena Excel di
     * region non-Inggris (mis. Indonesia) memakai titik koma sebagai
     * delimiter default, bukan koma — dilaporkan pengguna lewat tangkapan
     * layar. BOM UTF-8 + baris "sep=," di awal berkas memaksa Excel
     * selalu memakai koma apa pun region-nya.
     */
    public function test_template_csv_diawali_bom_dan_baris_sep_untuk_kompatibilitas_excel(): void
    {
        $response = $this->actingAs($this->sysAdmin())->get('/admin/sistem/pegawai/impor/contoh');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF"."sep=,\r\n", $content);
        $this->assertStringContainsString('nrp,nama,tanggal_lahir', $content);
    }

    /** Round-trip: berkas yang benar-benar diunduh (dengan baris sep=,) harus tetap bisa diunggah ulang tanpa diedit dulu. */
    public function test_template_csv_yang_diunduh_dapat_langsung_diimpor_ulang(): void
    {
        $template = $this->actingAs($this->sysAdmin())->get('/admin/sistem/pegawai/impor/contoh')->getContent();
        $csv = $template."2099.02.0009,Uji Round Trip,,,,2026-01-01,trainee,KC-MTR,OFC,,\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $response->assertSessionHas('sukses');
        $this->assertNotNull(
            DB::table('emp_new_employee_requests')
                ->get(['proposed_data'])
                ->first(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.02.0009')
        );
    }

    public function test_impor_dengan_kolom_hr_dan_data_pribadi_lengkap(): void
    {
        $atasanNrp = '2015.07.0088';
        $header = "nrp,nama,tanggal_masuk,status_kepegawaian,kode_kantor,kode_jabatan,status_kawin,tanggungan,tanggal_tetap,nrp_atasan,divisi,agama,nomor_ktp,nomor_npwp,bpjs_tenaga_kerja,bpjs_kesehatan,nomor_simpeda,nomor_tambora_rencana,tmt_pangkat,alamat,no_telepon,kontak_darurat_nama,kontak_darurat_hubungan,kontak_darurat_telepon,pendidikan_terakhir,pendidikan_jurusan\n";
        $csv = $header."2099.02.0020,Uji Impor Lengkap,2026-01-01,tetap,KC-MTR,OFC,menikah,1,2026-01-01,{$atasanNrp},Divisi Operasional,Islam,5271000000000020,12.345.678.9-012.020,BPJSTK-0020,BPJSKES-0020,SIMPEDA-0020,TAMBORA-0020,2026-01-01,Jl. Impor No. 20,081200000020,Kontak Impor,Saudara,081200000021,S1,Akuntansi\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);
        $response->assertSessionHas('sukses');

        $requestRow = DB::table('emp_new_employee_requests')->where('status', 'pending')
            ->get(['id', 'proposed_data'])
            ->first(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.02.0020');
        $this->assertNotNull($requestRow);

        $proposed = json_decode($requestRow->proposed_data, true);
        $this->assertSame('menikah', $proposed['marital_status']);
        $this->assertSame(1, $proposed['tanggungan']);
        $this->assertSame($this->employeeId($atasanNrp), $proposed['supervisor_id']);
        $this->assertSame('Divisi Operasional', $proposed['division']);
        $this->assertSame('Islam', $proposed['agama']);
        $this->assertSame('5271000000000020', $proposed['nomor_ktp']);
        $this->assertSame('Jl. Impor No. 20', $proposed['alamat']);
        $this->assertSame('S1', $proposed['pendidikan_terakhir']);

        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/pegawai-baru/{$requestRow->id}/setujui")->assertRedirect();

        $employee = DB::table('emp_employees')->where('nrp', '2099.02.0020')->first();
        $this->assertNotNull($employee);
        $this->assertSame('Divisi Operasional', $employee->division);
        $this->assertSame('Islam', $employee->agama);
    }

    public function test_impor_dengan_nrp_atasan_tidak_dikenal_tetap_diproses_tanpa_atasan(): void
    {
        $header = "nrp,nama,tanggal_masuk,status_kepegawaian,kode_kantor,kode_jabatan,nrp_atasan\n";
        $csv = $header."2099.02.0021,Uji Atasan Tidak Dikenal,2026-01-01,trainee,KC-MTR,OFC,NRP.TIDAK.ADA\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);
        $response->assertSessionHas('sukses');

        $requestRow = DB::table('emp_new_employee_requests')->where('status', 'pending')
            ->get(['proposed_data'])
            ->first(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.02.0021');
        $this->assertNotNull($requestRow);
        $this->assertNull(json_decode($requestRow->proposed_data, true)['supervisor_id']);
    }

    public function test_impor_status_tetap_tanpa_tanggal_tetap_dilewati(): void
    {
        $header = "nrp,nama,tanggal_masuk,status_kepegawaian,kode_kantor,kode_jabatan\n";
        $csv = $header."2099.02.0022,Uji Tetap Tanpa Tanggal,2026-01-01,tetap,KC-MTR,OFC\n";
        $file = UploadedFile::fake()->createWithContent('pegawai.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pegawai/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('emp_new_employee_requests')
            ->get(['proposed_data'])
            ->filter(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.02.0022')
            ->count());
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/pegawai/impor');

        $response->assertForbidden();
    }

    private function sysAdmin(): User
    {
        return $this->userWithNrp('SYSADMIN');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
