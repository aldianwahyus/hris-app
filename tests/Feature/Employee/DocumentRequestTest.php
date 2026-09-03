<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Notifications\RequestDecided;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Layanan Dokumen Mandiri — modul baru (evaluasi PM/client
 * 2026-09-02). SATU tahap (pola PERSIS Izin): hr_admin lingkup
 * kantornya sendiri, hr_approver seluruh bank.
 */
final class DocumentRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_mengajukan_dan_melihat_riwayat(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($pegawai)->post('/dokumen/ajukan', [
            'document_type' => 'surat_keterangan_kerja',
            'purpose' => 'Persyaratan pengajuan KPR.',
        ]);

        $response->assertRedirect(route('documents.history'));
        $row = DB::table('doc_requests')->where('employee_id', $pegawai->employee_id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);

        $history = $this->actingAs($pegawai)->get('/dokumen/riwayat');
        $history->assertOk();
        $history->assertSeeText('Surat Keterangan Kerja');
    }

    public function test_jenis_dokumen_tidak_valid_ditolak(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($pegawai)->post('/dokumen/ajukan', [
            'document_type' => 'jenis_tidak_ada',
            'purpose' => 'Keperluan uji.',
        ]);

        $response->assertSessionHasErrors('document_type');
        $this->assertSame(0, DB::table('doc_requests')->where('employee_id', $pegawai->employee_id)->count());
    }

    public function test_hr_admin_hanya_melihat_permintaan_kantornya_sendiri(): void
    {
        $this->submitRequest('2021.05.0302'); // Rina Marlina — hr_admin KCP Gerung, mengajukan sendiri
        $this->submitRequest('2018.03.0142'); // Siti Rahmawati — kantor lain

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/persetujuan/dokumen');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertDontSeeText('Siti Rahmawati');
    }

    public function test_penolakan_menyimpan_catatan_dan_mengirim_notifikasi(): void
    {
        Notification::fake();
        $id = $this->submitRequest('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061')) // Nur Aisyah, hr_approver
            ->post("/persetujuan/dokumen/{$id}/tolak", ['catatan' => 'Keperluan tidak jelas, mohon lengkapi.']);

        $response->assertRedirect(route('admin.document-request-queue'));
        $row = DB::table('doc_requests')->where('id', $id)->first();
        $this->assertSame('ditolak', $row->status);
        $this->assertSame('Keperluan tidak jelas, mohon lengkapi.', $row->decision_note);

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === false && $n->reason === 'Keperluan tidak jelas, mohon lengkapi.',
        );
    }

    public function test_penerbitan_mengizinkan_unduh_dan_mengirim_notifikasi(): void
    {
        Notification::fake();
        $id = $this->submitRequest('2018.03.0142');

        $issue = $this->actingAs($this->userWithNrp('2014.02.0061'))->post("/persetujuan/dokumen/{$id}/setujui");
        $issue->assertRedirect(route('admin.document-request-queue'));
        $this->assertSame('siap', DB::table('doc_requests')->where('id', $id)->value('status'));

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === true,
        );

        $download = $this->actingAs($this->userWithNrp('2018.03.0142'))->get("/dokumen/{$id}/unduh");
        $download->assertOk();
        $download->assertHeader('content-type', 'application/pdf');
    }

    public function test_tidak_bisa_mengunduh_dokumen_yang_belum_diterbitkan(): void
    {
        $id = $this->submitRequest('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get("/dokumen/{$id}/unduh");

        $response->assertNotFound();
    }

    public function test_permintaan_yang_sudah_diproses_tidak_bisa_diproses_ulang(): void
    {
        $id = $this->submitRequest('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $this->actingAs($hrApprover)->post("/persetujuan/dokumen/{$id}/setujui");
        $response = $this->actingAs($hrApprover)->post("/persetujuan/dokumen/{$id}/tolak", ['catatan' => 'Terlambat.']);

        $response->assertNotFound();
        $this->assertSame('siap', DB::table('doc_requests')->where('id', $id)->value('status'));
    }

    public function test_hc_dapat_menandatangani_dokumen_setelah_diterbitkan(): void
    {
        $id = $this->submitRequest('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/dokumen/{$id}/setujui");

        $response = $this->actingAs($hrApprover)->post("/tanda-tangan/document_request/{$id}", [
            'typed_name' => 'Nur Aisyah',
        ]);

        $response->assertRedirect(route('admin.document-request-queue'));
        $signature = DB::table('sig_signatures')->where('signable_type', 'document_request')->where('signable_id', $id)->first();
        $this->assertNotNull($signature);
        $this->assertSame('Nur Aisyah', $signature->typed_name);
    }

    private function submitRequest(string $nrp): string
    {
        $pegawai = $this->userWithNrp($nrp);

        $this->actingAs($pegawai)->post('/dokumen/ajukan', [
            'document_type' => 'surat_keterangan_kerja',
            'purpose' => 'Keperluan uji otomatis.',
        ]);

        return DB::table('doc_requests')->where('employee_id', $pegawai->employee_id)->latest('created_at')->value('id');
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
