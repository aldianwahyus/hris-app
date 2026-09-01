<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "CV Saya" (ESS) — data pribadi diubah pegawai sendiri TANPA
 * persetujuan (beda dari data organisasi lewat maker-checker).
 */
final class EmployeeCvTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_melihat_cv_miliknya(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($siti)->get('/cv-saya');

        $response->assertOk();
        $response->assertSeeText('Siti Rahmawati');
        $response->assertSeeText('CV Saya');
    }

    public function test_pegawai_dapat_mengubah_data_pribadi_langsung_tanpa_persetujuan(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($siti)->post('/cv-saya', [
            'alamat' => 'Jl. Contoh No. 1, Mataram',
            'no_telepon' => '081234567890',
            'kontak_darurat_nama' => 'Budi Santoso',
            'kontak_darurat_hubungan' => 'Suami',
            'kontak_darurat_telepon' => '081298765432',
            'pendidikan_terakhir' => 'S1',
            'pendidikan_jurusan' => 'Akuntansi',
        ]);

        $response->assertRedirect(route('ess.cv'));
        $response->assertSessionHas('sukses');

        // Langsung tersimpan — TIDAK ada baris pending menunggu siapa pun.
        $employee = DB::table('emp_employees')->where('id', $siti->employee_id)->first();
        $this->assertSame('Jl. Contoh No. 1, Mataram', $employee->alamat);
        $this->assertSame('081234567890', $employee->no_telepon);
        $this->assertSame('Budi Santoso', $employee->kontak_darurat_nama);
        $this->assertSame('S1', $employee->pendidikan_terakhir);
        $this->assertSame('Akuntansi', $employee->pendidikan_jurusan);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'employee_personal_details')
            ->where('auditable_id', $siti->employee_id)
            ->first();
        $this->assertNotNull($audit);
    }

    /**
     * Regresi (bug ditemukan lewat audit kode): UpdateOwnPersonalDetails
     * sebelumnya menjalankan UPDATE ... WHERE version = $stale TANPA
     * memeriksa jumlah baris terdampak — kalau version sudah berubah
     * duluan (mis. approval data organisasi menaikkan version di antara
     * baca dan tulis), UPDATE itu mencocokkan 0 baris, TIDAK melakukan
     * apa pun, tapi kode tetap menulis audit trail "berhasil" dan
     * redirect sukses — perubahan pegawai hilang diam-diam.
     */
    public function test_perubahan_ditolak_jika_versi_data_sudah_berubah_sejak_dibaca(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        // UpdateOwnPersonalDetails::handle() membaca $current SEKALI lalu
        // memakai version itu untuk WHERE klausa UPDATE-nya — menaikkan
        // version SEBELUM permintaan dikirim tidak menguji apa pun (kelas
        // itu akan membaca version yang SUDAH naik dan tetap cocok).
        // Race sungguhan hanya terjadi ANTARA baca dan tulisnya SENDIRI,
        // jadi disimulasikan lewat DB::listen(): begitu SELECT pertama
        // kelas itu selesai (mendapati version LAMA), suntikkan UPDATE
        // mentah yang menaikkan version — persis proses lain yang
        // menyelesaikan tulisannya duluan di sela-sela permintaan ini.
        DB::listen(function ($query) use ($siti) {
            if (str_contains($query->sql, 'select * from "emp_employees" where "id" = ?')
                && ($query->bindings[0] ?? null) === $siti->employee_id) {
                DB::table('emp_employees')->where('id', $siti->employee_id)->increment('version');
            }
        });

        $response = $this->actingAs($siti)->post('/cv-saya', [
            'alamat' => 'Jl. Basi No. 1, Mataram',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('gagal');

        $employee = DB::table('emp_employees')->where('id', $siti->employee_id)->first();
        $this->assertNotSame('Jl. Basi No. 1, Mataram', $employee->alamat, 'Perubahan tidak boleh tersimpan saat konflik versi.');

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'employee_personal_details')
            ->where('auditable_id', $siti->employee_id)
            ->where('new_values', 'like', '%Basi%')
            ->first();
        $this->assertNull($audit, 'Tidak boleh ada entri audit "berhasil" untuk perubahan yang sebenarnya gagal.');
    }

    public function test_field_organisasi_tidak_bisa_diubah_lewat_cv_saya(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $originalPositionId = DB::table('emp_employees')->where('id', $siti->employee_id)->value('position_id');
        $otherPositionId = DB::table('md_positions')->where('id', '!=', $originalPositionId)->value('id');

        $this->actingAs($siti)->post('/cv-saya', [
            'alamat' => 'Alamat baru',
            'position_id' => $otherPositionId, // BUKAN field yang di-whitelist — harus diabaikan
        ]);

        $this->assertSame(
            $originalPositionId,
            DB::table('emp_employees')->where('id', $siti->employee_id)->value('position_id'),
            'position_id tidak boleh berubah lewat CV Saya — itu data organisasi (maker-checker).'
        );
        $this->assertSame('Alamat baru', DB::table('emp_employees')->where('id', $siti->employee_id)->value('alamat'));
    }

    public function test_field_identitas_hr_tidak_bisa_diubah_lewat_cv_saya(): void
    {
        // Agama/KTP/NPWP/BPJS/Simpeda/Tambora/TMT Pangkat sengaja lewat
        // maker-checker (EditableEmployeeField), BUKAN CV Saya, walau
        // terasa "data pribadi" — keputusan sadar HC.
        $siti = $this->userWithNrp('2018.03.0142');

        $this->actingAs($siti)->post('/cv-saya', [
            'alamat' => 'Alamat baru',
            'nomor_ktp' => '5271012345670001',
            'agama' => 'Islam',
        ]);

        $employee = DB::table('emp_employees')->where('id', $siti->employee_id)->first();
        $this->assertNull($employee->nomor_ktp);
        $this->assertNull($employee->agama);
        $this->assertSame('Alamat baru', $employee->alamat);
    }

    public function test_pegawai_dapat_mengunggah_foto_profil_langsung_tanpa_persetujuan(): void
    {
        Storage::fake('s3');
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($siti)->post('/cv-saya/foto', [
            'photo' => UploadedFile::fake()->image('foto-siti.jpg'),
        ]);

        $response->assertRedirect(route('ess.cv'));
        $response->assertSessionHas('sukses');

        $photoPath = DB::table('emp_employees')->where('id', $siti->employee_id)->value('photo_path');
        $this->assertNotNull($photoPath);
        Storage::disk('s3')->assertExists($photoPath);

        $view = $this->actingAs($siti)->get('/cv-saya/foto');
        $view->assertOk();
    }

    public function test_foto_format_tidak_didukung_ditolak(): void
    {
        Storage::fake('s3');
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($siti)->post('/cv-saya/foto', [
            'photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrorsIn('photo', ['photo']);
        $this->assertNull(DB::table('emp_employees')->where('id', $siti->employee_id)->value('photo_path'));
    }

    public function test_foto_melebihi_2mb_ditolak(): void
    {
        Storage::fake('s3');
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($siti)->post('/cv-saya/foto', [
            'photo' => UploadedFile::fake()->image('besar.jpg')->size(2049),
        ]);

        $response->assertSessionHasErrorsIn('photo', ['photo']);
        $this->assertNull(DB::table('emp_employees')->where('id', $siti->employee_id)->value('photo_path'));
    }

    public function test_pegawai_dapat_menghapus_foto_profil(): void
    {
        Storage::fake('s3');
        $siti = $this->userWithNrp('2018.03.0142');

        $this->actingAs($siti)->post('/cv-saya/foto', ['photo' => UploadedFile::fake()->image('foto-siti.jpg')]);
        $photoPath = DB::table('emp_employees')->where('id', $siti->employee_id)->value('photo_path');

        $response = $this->actingAs($siti)->delete('/cv-saya/foto');

        $response->assertRedirect(route('ess.cv'));
        $this->assertNull(DB::table('emp_employees')->where('id', $siti->employee_id)->value('photo_path'));
        Storage::disk('s3')->assertMissing($photoPath);
    }

    /**
     * Regresi: photo_path masuk whitelist SelfEditableEmployeeField (supaya
     * UpdateOwnPersonalDetails::handle() bisa menerimanya dari
     * updatePhoto()), TAPI form data pribadi teks (POST /cv-saya) TIDAK
     * PERNAH menyertakan kolom ini dalam validasinya sendiri — tanpa
     * pengecekan array_key_exists di EmployeeCvController::update(),
     * foto yang sudah ada akan diam-diam terhapus setiap kali form teks
     * ini disimpan.
     */
    public function test_menyimpan_data_pribadi_teks_tidak_menghapus_foto_yang_sudah_ada(): void
    {
        Storage::fake('s3');
        $siti = $this->userWithNrp('2018.03.0142');

        $this->actingAs($siti)->post('/cv-saya/foto', ['photo' => UploadedFile::fake()->image('foto-siti.jpg')]);
        $photoPath = DB::table('emp_employees')->where('id', $siti->employee_id)->value('photo_path');
        $this->assertNotNull($photoPath);

        $this->actingAs($siti)->post('/cv-saya', ['alamat' => 'Alamat baru saja']);

        $this->assertSame($photoPath, DB::table('emp_employees')->where('id', $siti->employee_id)->value('photo_path'));
    }

    public function test_pegawai_lain_tidak_bisa_melihat_cv_orang_lain(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $hendra = $this->userWithNrp('2017.11.0119');

        $this->actingAs($hendra)->post('/cv-saya', ['alamat' => 'Alamat Hendra']);

        $this->assertNull(DB::table('emp_employees')->where('id', $siti->employee_id)->value('alamat'));
        $this->assertSame('Alamat Hendra', DB::table('emp_employees')->where('id', $hendra->employee_id)->value('alamat'));
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
