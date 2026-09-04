<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perkakas Data Pribadi (UU PDP, Fase 2) — unduh data sendiri +
 * pengajuan penghapusan (ditinjau MANUAL hr_approver, BUKAN otomatis).
 */
final class PrivacyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_mengunduh_data_sendiri(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($pegawai)->get('/privasi-saya/unduh');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertJsonStructure([
            'diekspor_pada',
            'profil',
            'riwayat_cuti',
            'riwayat_izin',
            'riwayat_dokumen_mandiri',
            'riwayat_tiket_bantuan',
        ]);
    }

    public function test_pegawai_dapat_mengajukan_penghapusan_data(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($pegawai)->post('/privasi-saya/hapus', [
            'reason' => 'Sudah tidak bekerja lagi, mohon data pribadi dihapus.',
        ]);

        $response->assertRedirect(route('privacy.index'));
        $row = DB::table('pdp_deletion_requests')->where('employee_id', $pegawai->employee_id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
    }

    public function test_permintaan_penghapusan_ganda_ditolak_selagi_masih_pending(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');
        $this->actingAs($pegawai)->post('/privasi-saya/hapus', ['reason' => 'Alasan pertama.']);

        $response = $this->actingAs($pegawai)->post('/privasi-saya/hapus', ['reason' => 'Alasan kedua.']);

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('pdp_deletion_requests')->where('employee_id', $pegawai->employee_id)->count());
    }

    public function test_hr_approver_dapat_meninjau_lalu_menuntaskan_permintaan(): void
    {
        $id = $this->submitDeletionRequest('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $review = $this->actingAs($hrApprover)->post("/persetujuan/privasi/{$id}/tinjau");
        $review->assertRedirect(route('admin.privacy-request-queue'));
        $this->assertSame('reviewed', DB::table('pdp_deletion_requests')->where('id', $id)->value('status'));

        $complete = $this->actingAs($hrApprover)->post("/persetujuan/privasi/{$id}/tuntaskan", [
            'catatan' => 'Data pribadi telah dianonimkan sesuai kebijakan retensi.',
        ]);
        $complete->assertRedirect(route('admin.privacy-request-queue'));
        $row = DB::table('pdp_deletion_requests')->where('id', $id)->first();
        $this->assertSame('completed', $row->status);
        $this->assertSame('Data pribadi telah dianonimkan sesuai kebijakan retensi.', $row->notes);
    }

    public function test_tidak_bisa_menuntaskan_permintaan_yang_belum_ditinjau(): void
    {
        $id = $this->submitDeletionRequest('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/privasi/{$id}/tuntaskan");

        $response->assertRedirect(route('admin.privacy-request-queue'));
        $response->assertSessionHas('gagal');
        $this->assertSame('pending', DB::table('pdp_deletion_requests')->where('id', $id)->value('status'));
    }

    public function test_hr_approver_dapat_menolak_permintaan(): void
    {
        $id = $this->submitDeletionRequest('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/privasi/{$id}/tolak", [
            'catatan' => 'Belum memenuhi syarat penghapusan.',
        ]);

        $response->assertRedirect(route('admin.privacy-request-queue'));
        $row = DB::table('pdp_deletion_requests')->where('id', $id)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame('Belum memenuhi syarat penghapusan.', $row->notes);
    }

    public function test_hr_admin_tidak_bisa_mengakses_antrean_privasi(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina Marlina — hr_admin, BUKAN hr_approver

        $response = $this->actingAs($hrAdmin)->get('/persetujuan/privasi');

        $response->assertForbidden();
    }

    private function submitDeletionRequest(string $nrp): string
    {
        $pegawai = $this->userWithNrp($nrp);

        $this->actingAs($pegawai)->post('/privasi-saya/hapus', ['reason' => 'Keperluan uji otomatis.']);

        return DB::table('pdp_deletion_requests')->where('employee_id', $pegawai->employee_id)->latest('created_at')->value('id');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        return User::query()->where('employee_id', $this->employeeId($nrp))->firstOrFail();
    }
}
