<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Whistleblowing/Pengaduan (Fase 2) — modul BARU, SENGAJA terpisah
 * dari Helpdesk. Akses antrean HANYA hr_approver (BUKAN hr_admin).
 */
final class WhistleblowingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_mengajukan_laporan_non_anonim(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($pegawai)->post('/pengaduan', [
            'category' => 'fraud',
            'description' => 'Dugaan penyimpangan pencatatan transaksi di kantor cabang.',
        ]);

        $response->assertRedirect(route('whistleblowing.index'));
        $row = DB::table('wb_reports')->where('reporter_employee_id', $pegawai->employee_id)->first();
        $this->assertNotNull($row);
        $this->assertFalse((bool) $row->is_anonymous);
        $this->assertSame('baru', $row->status);

        $history = $this->actingAs($pegawai)->get('/pengaduan');
        $history->assertOk();
        $history->assertSeeText('Kecurangan/Fraud');
    }

    public function test_laporan_anonim_tidak_menyimpan_identitas_pelapor(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $this->actingAs($pegawai)->post('/pengaduan', [
            'category' => 'harassment',
            'description' => 'Dugaan pelecehan oleh atasan di unit kerja.',
            'is_anonymous' => '1',
        ]);

        $row = DB::table('wb_reports')->where('category', 'harassment')->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_anonymous);
        $this->assertNull($row->reporter_employee_id);

        // Jejak audit TIDAK boleh membocorkan identitas pelapor anonim.
        $auditRow = DB::table('aud_change_logs')
            ->where('auditable_type', 'wb_report')
            ->where('auditable_id', $row->id)
            ->first();
        $this->assertNotNull($auditRow);
        $this->assertNull($auditRow->actor_id);
        $this->assertNull($auditRow->ip_address);
        $this->assertNull($auditRow->user_agent);
    }

    public function test_laporan_anonim_tidak_muncul_di_riwayat_sendiri(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $this->actingAs($pegawai)->post('/pengaduan', [
            'category' => 'other',
            'description' => 'Laporan uji anonim.',
            'is_anonymous' => '1',
        ]);

        $history = $this->actingAs($pegawai)->get('/pengaduan');

        $history->assertOk();
        $history->assertSeeText('Belum ada laporan non-anonim');
    }

    public function test_kategori_tidak_valid_ditolak(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($pegawai)->post('/pengaduan', [
            'category' => 'kategori_tidak_ada',
            'description' => 'Uji kategori tidak valid.',
        ]);

        $response->assertSessionHasErrors('category');
    }

    public function test_hr_approver_dapat_melihat_antrean_dan_menindaklanjuti(): void
    {
        $id = $this->submitReport('2018.03.0142', 'fraud', false);
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $queue = $this->actingAs($hrApprover)->get('/persetujuan/pengaduan');
        $queue->assertOk();
        $queue->assertSeeText('Siti Rahmawati');

        $start = $this->actingAs($hrApprover)->post("/persetujuan/pengaduan/{$id}/proses");
        $start->assertRedirect(route('admin.whistleblowing-show', $id));
        $this->assertSame('diproses', DB::table('wb_reports')->where('id', $id)->value('status'));

        $complete = $this->actingAs($hrApprover)->post("/persetujuan/pengaduan/{$id}/selesai", [
            'resolution_notes' => 'Sudah diinvestigasi, tidak ditemukan pelanggaran.',
        ]);
        $complete->assertRedirect(route('admin.whistleblowing-queue'));
        $row = DB::table('wb_reports')->where('id', $id)->first();
        $this->assertSame('selesai', $row->status);
        $this->assertSame('Sudah diinvestigasi, tidak ditemukan pelanggaran.', $row->resolution_notes);
    }

    public function test_tidak_bisa_menuntaskan_laporan_yang_belum_diproses(): void
    {
        $id = $this->submitReport('2018.03.0142', 'fraud', false);
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/pengaduan/{$id}/selesai", [
            'resolution_notes' => 'Uji transisi salah.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('baru', DB::table('wb_reports')->where('id', $id)->value('status'));
    }

    public function test_hr_admin_tidak_bisa_mengakses_antrean_pengaduan(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina Marlina — hr_admin, BUKAN hr_approver

        $response = $this->actingAs($hrAdmin)->get('/persetujuan/pengaduan');

        $response->assertForbidden();
    }

    private function submitReport(string $nrp, string $category, bool $isAnonymous): string
    {
        $pegawai = $this->userWithNrp($nrp);

        $this->actingAs($pegawai)->post('/pengaduan', [
            'category' => $category,
            'description' => 'Laporan uji otomatis.',
            'is_anonymous' => $isAnonymous ? '1' : '0',
        ]);

        return DB::table('wb_reports')
            ->where('reporter_employee_id', $isAnonymous ? null : $pegawai->employee_id)
            ->latest('created_at')
            ->value('id');
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
