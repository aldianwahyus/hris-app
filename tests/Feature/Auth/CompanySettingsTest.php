<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Interfaces\Http\Support\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengaturan Perusahaan (Fase 2) — nama+lambang dinamis (permission
 * sysadmin-content.manage, SAMA Menu Aplikasi Mobile/Kalender Libur/
 * dst.), lihat CompanyProfile/CompanySettingsController.
 */
final class CompanySettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_melihat_dan_mengubah_nama_perusahaan(): void
    {
        $index = $this->actingAs($this->sysAdmin())->get('/admin/sistem/pengaturan-perusahaan');
        $index->assertOk();
        // Nama perusahaan dirender sebagai VALUE atribut <input>, bukan
        // teks halaman biasa — assertSeeText (strip_tags) tidak melihat
        // atribut sama sekali, jadi dicek lewat HTML mentah di sini.
        $index->assertSee('value="PT Bank NTB Syariah"', false);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pengaturan-perusahaan', [
            'company_name' => 'PT Bank NTB Syariah Tbk',
        ]);

        $response->assertRedirect(route('sysadmin.company-settings.index'));
        $response->assertSessionHas('sukses');
        $this->assertSame('PT Bank NTB Syariah Tbk', DB::table('company_settings')->value('company_name'));

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'company_settings')
            ->where('action', 'updated')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_nama_perusahaan_wajib_diisi(): void
    {
        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pengaturan-perusahaan', [
            'company_name' => '',
        ]);

        $response->assertSessionHasErrors('company_name');
    }

    public function test_hr_approver_dapat_mengubah_pengaturan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061')) // hr_approver
            ->post('/admin/sistem/pengaturan-perusahaan', ['company_name' => 'PT Bank NTB Syariah']);

        $response->assertRedirect(route('sysadmin.company-settings.index'));
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/pengaturan-perusahaan');

        $response->assertForbidden();
    }

    public function test_unggah_lambang_baru_memperbarui_logo_path_dan_menghapus_lambang_lama(): void
    {
        Storage::fake('s3');

        $firstUpload = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pengaturan-perusahaan', [
            'company_name' => 'PT Bank NTB Syariah',
            'logo' => UploadedFile::fake()->image('lambang-lama.png'),
        ]);
        $firstUpload->assertRedirect(route('sysadmin.company-settings.index'));
        $oldPath = DB::table('company_settings')->value('logo_path');
        $this->assertNotNull($oldPath);
        Storage::disk('s3')->assertExists($oldPath);

        $secondUpload = $this->actingAs($this->sysAdmin())->post('/admin/sistem/pengaturan-perusahaan', [
            'company_name' => 'PT Bank NTB Syariah',
            'logo' => UploadedFile::fake()->image('lambang-baru.png'),
        ]);
        $secondUpload->assertRedirect(route('sysadmin.company-settings.index'));
        $newPath = DB::table('company_settings')->value('logo_path');

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('s3')->assertExists($newPath);
        Storage::disk('s3')->assertMissing($oldPath);
    }

    public function test_company_profile_mengembalikan_nilai_bawaan_saat_belum_diubah(): void
    {
        $this->assertSame('PT Bank NTB Syariah', CompanyProfile::name());
        $this->assertStringStartsWith('data:image/png;base64,', CompanyProfile::logoDataUri());
    }

    public function test_company_profile_mencerminkan_perubahan_nama(): void
    {
        DB::table('company_settings')->update(['company_name' => 'PT Bank NTB Syariah Baru']);

        $this->assertSame('PT Bank NTB Syariah Baru', CompanyProfile::name());
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
