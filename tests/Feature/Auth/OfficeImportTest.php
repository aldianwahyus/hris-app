<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Impor massal kantor dari CSV — TIDAK seperti impor pegawai
 * (maker-checker), md_offices TIDAK punya antrean persetujuan sama
 * sekali (lihat ImportOffices/OfficeController): baris yang lolos
 * LANGSUNG jadi kantor aktif.
 */
final class OfficeImportTest extends TestCase
{
    use DatabaseTransactions;

    private const HEADER = "kode,nama,tipe,zona_waktu,alamat,kelas,kode_kantor_induk\n";

    public function test_impor_valid_langsung_membuat_kantor_aktif(): void
    {
        $csv = self::HEADER."KC-UJI,KC Uji Impor,branch,Asia/Makassar,Jl. Uji No. 1,KC_1,\n";
        $file = UploadedFile::fake()->createWithContent('kantor.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor/impor', ['berkas' => $file]);

        $response->assertRedirect(route('sysadmin.offices.import.index'));
        $response->assertSessionHas('sukses');

        $office = DB::table('md_offices')->where('code', 'KC-UJI')->first();
        $this->assertNotNull($office);
        $this->assertSame('KC Uji Impor', $office->name);
        $this->assertTrue((bool) $office->is_active);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'md_office')->where('auditable_id', $office->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($audit);
    }

    public function test_kode_kantor_duplikat_dilewati_dengan_alasan(): void
    {
        $csv = self::HEADER."KC-MTR,Nama Baru,branch,Asia/Makassar,,,\n"; // KC-MTR sudah ada di data contoh
        $file = UploadedFile::fake()->createWithContent('kantor.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('dilewati', session('gagal'));
        $this->assertSame('KC Mataram', DB::table('md_offices')->where('code', 'KC-MTR')->value('name'));
    }

    public function test_kode_kantor_induk_tidak_dikenal_dilewati(): void
    {
        $csv = self::HEADER."KC-UJI2,KC Uji Dua,branch,Asia/Makassar,,,TIDAK-ADA\n";
        $file = UploadedFile::fake()->createWithContent('kantor.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
        $this->assertNull(DB::table('md_offices')->where('code', 'KC-UJI2')->first());
    }

    /**
     * Baris DIPROSES SATU PER SATU (bukan batch di akhir) — kantor
     * INDUK di baris pertama sudah tersimpan saat baris ANAK (baris
     * kedua) diproses, jadi kode_kantor_induk bisa merujuknya TANPA
     * perlu dua kali impor terpisah.
     */
    public function test_kode_kantor_induk_dapat_merujuk_kantor_baru_pada_baris_sebelumnya(): void
    {
        $csv = self::HEADER
            ."KC-INDUK,KC Induk Uji,branch,Asia/Makassar,,,\n"
            ."KCP-ANAK,KCP Anak Uji,sub_branch,Asia/Makassar,,,KC-INDUK\n";
        $file = UploadedFile::fake()->createWithContent('kantor.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor/impor', ['berkas' => $file]);

        $response->assertSessionHas('sukses');

        $indukId = DB::table('md_offices')->where('code', 'KC-INDUK')->value('id');
        $this->assertNotNull($indukId);
        $this->assertSame($indukId, DB::table('md_offices')->where('code', 'KCP-ANAK')->value('parent_office_id'));
    }

    public function test_tipe_kantor_tidak_valid_dilewati(): void
    {
        $csv = self::HEADER."KC-UJI3,KC Uji Tiga,bukan_tipe,Asia/Makassar,,,\n";
        $file = UploadedFile::fake()->createWithContent('kantor.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
        $this->assertNull(DB::table('md_offices')->where('code', 'KC-UJI3')->first());
    }

    public function test_header_tanpa_kolom_wajib_ditolak(): void
    {
        $csv = "kode,nama\nKC-UJI4,KC Uji Empat\n";
        $file = UploadedFile::fake()->createWithContent('kantor.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor/impor', ['berkas' => $file]);

        $response->assertSessionHas('gagal');
    }

    /** Pola SAMA PERSIS EmployeeImportTest — BOM UTF-8 + baris "sep=," memaksa Excel selalu memakai koma apa pun region-nya. */
    public function test_template_csv_diawali_bom_dan_baris_sep_untuk_kompatibilitas_excel(): void
    {
        $response = $this->actingAs($this->sysAdmin())->get('/admin/sistem/daftar-kantor/impor/contoh');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF"."sep=,\r\n", $content);
        $this->assertStringContainsString('kode,nama,tipe,zona_waktu', $content);
    }

    /** Round-trip: berkas yang benar-benar diunduh harus tetap bisa diunggah ulang tanpa diedit dulu. */
    public function test_template_csv_yang_diunduh_dapat_langsung_diimpor_ulang(): void
    {
        $template = $this->actingAs($this->sysAdmin())->get('/admin/sistem/daftar-kantor/impor/contoh')->getContent();

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/daftar-kantor/impor', [
            'berkas' => UploadedFile::fake()->createWithContent('kantor.csv', $template),
        ]);

        $response->assertSessionHas('sukses');
        $this->assertNotNull(DB::table('md_offices')->where('code', 'KC-BIMA')->first());
    }

    public function test_hr_approver_dapat_mengimpor_kantor(): void
    {
        $csv = self::HEADER."KC-UJI5,KC Uji Lima,branch,Asia/Makassar,,,\n";
        $file = UploadedFile::fake()->createWithContent('kantor.csv', $csv);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post('/admin/sistem/daftar-kantor/impor', ['berkas' => $file]);

        $response->assertSessionHas('sukses');
        $this->assertNotNull(DB::table('md_offices')->where('code', 'KC-UJI5')->first());
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/daftar-kantor/impor');

        $response->assertForbidden();
    }

    private function sysAdmin(): User
    {
        return $this->userWithNrp('SYSADMIN');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
