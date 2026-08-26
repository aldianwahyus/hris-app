<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Digital Library (BRD §5.7) — repository konten, search & filter,
 * tracking aktivitas (lms_library_access_logs setiap open()).
 */
final class LmsLibraryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hc_dapat_mengunggah_materi_berkas(): void
    {
        Storage::fake('s3');
        $file = UploadedFile::fake()->create('modul-k3.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/perpustakaan', [
            'title' => 'Modul K3',
            'category' => 'Kepatuhan',
            'berkas' => $file,
        ]);

        $response->assertRedirect(route('lms.admin.library.index'));
        $item = DB::table('lms_library_items')->where('title', 'Modul K3')->first();
        $this->assertNotNull($item);
        $this->assertNotNull($item->file_path);
        Storage::disk('s3')->assertExists($item->file_path);
    }

    public function test_hc_dapat_menambah_materi_tautan_eksternal(): void
    {
        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/perpustakaan', [
            'title' => 'Video Sharia Compliance',
            'external_url' => 'https://example.com/video',
        ]);

        $response->assertRedirect(route('lms.admin.library.index'));
        $this->assertSame(1, DB::table('lms_library_items')->where('title', 'Video Sharia Compliance')->count());
    }

    public function test_tanpa_berkas_dan_tautan_ditolak(): void
    {
        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/perpustakaan', [
            'title' => 'Materi Kosong',
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('lms_library_items')->where('title', 'Materi Kosong')->count());
    }

    public function test_pegawai_biasa_dapat_menjelajah_tanpa_permission_khusus(): void
    {
        $this->seedItem('Modul Terbuka', externalUrl: 'https://example.com/a');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/pelatihan/perpustakaan');

        $response->assertOk();
        $response->assertSeeText('Modul Terbuka');
    }

    public function test_membuka_materi_mencatat_akses(): void
    {
        $itemId = $this->seedItem('Modul Dicatat', externalUrl: 'https://example.com/b');
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($siti)->get("/pelatihan/perpustakaan/{$itemId}/buka");

        $response->assertRedirect('https://example.com/b');
        $log = DB::table('lms_library_access_logs')->where('library_item_id', $itemId)->where('employee_id', $siti->employee_id)->first();
        $this->assertNotNull($log);
    }

    public function test_materi_nonaktif_tidak_tampil_di_listing_ess_tapi_tetap_di_admin(): void
    {
        $itemId = $this->seedItem('Modul Nonaktif', externalUrl: 'https://example.com/c');
        DB::table('lms_library_items')->where('id', $itemId)->update(['is_active' => false]);

        $ess = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/pelatihan/perpustakaan');
        $ess->assertDontSeeText('Modul Nonaktif');

        // Judul di halaman admin dirender sebagai VALUE input (baris
        // bisa diedit langsung, pola sama Daftar Kantor/Jabatan) — bukan
        // teks polos, jadi assertSee (mentah) bukan assertSeeText.
        $admin = $this->actingAs($this->hrAdmin())->get('/admin/pelatihan/perpustakaan');
        $admin->assertSee('Modul Nonaktif', false);
    }

    public function test_filter_kategori_dan_pencarian_bekerja(): void
    {
        $this->seedItem('Panduan Anti Fraud', category: 'Kepatuhan', externalUrl: 'https://example.com/d');
        $this->seedItem('Panduan Layanan Prima', category: 'Layanan', externalUrl: 'https://example.com/e');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->get('/pelatihan/perpustakaan?'.http_build_query(['kategori' => 'Kepatuhan']));

        $response->assertSeeText('Panduan Anti Fraud');
        $response->assertDontSeeText('Panduan Layanan Prima');
    }

    public function test_peran_lain_ditolak_dari_admin_perpustakaan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/pelatihan/perpustakaan');

        $response->assertForbidden();
    }

    private function seedItem(string $title, ?string $category = null, ?string $externalUrl = null): string
    {
        $id = (string) Uuid7::generate();

        DB::table('lms_library_items')->insert([
            'id' => $id,
            'title' => $title,
            'category' => $category,
            'external_url' => $externalUrl,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function hrAdmin(): User
    {
        return $this->userWithNrp('2021.05.0302');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
