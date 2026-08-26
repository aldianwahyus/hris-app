<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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
