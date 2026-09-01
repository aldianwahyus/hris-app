<?php

declare(strict_types=1);

namespace Tests\Feature\Sppd;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Notifications\RequestDecided;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * SPPD SEKARANG 2 TAHAP SERAGAM untuk SEMUA trip_category (koreksi atas
 * pemilahan lama per kategori — lihat SppdApprovalController): Atasan
 * Langsung dulu, baru Pimpinan Kantor. hr_approver DIHAPUS dari jalur
 * KEPUTUSAN. Pola PERSIS LeaveApprovalQueueScopeTest.
 */
final class SppdApprovalScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_approver_tidak_lagi_bisa_akses_antrean_sppd(): void
    {
        // Nur Aisyah kebetulan JUGA pimpinan_kantor Kantor Pusat di data
        // demo — cabut peran itu sementara supaya test ini murni menguji
        // hr_approver (bukan tertolong lolos lewat peran lain yang sah).
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->revokeRole($hrApprover, 'pimpinan_kantor');

        $response = $this->actingAs($hrApprover)->get('/persetujuan/sppd');

        $response->assertForbidden();
    }

    public function test_atasan_langsung_setuju_menaikkan_ke_tahap_pimpinan_bukan_final(): void
    {
        $requestId = $this->insertSppdRequest($this->employeeId('2018.03.0142'), 'pending'); // Siti, KC Mataram

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/sppd/{$requestId}/setujui");

        $response->assertRedirect(route('admin.sppd-approval-queue'));

        $row = DB::table('spd_requests')->where('id', $requestId)->first();
        $this->assertSame('pending_pimpinan', $row->status);
        $this->assertSame($this->employeeId('2015.07.0088'), $row->atasan_approver_id);
        $this->assertNull($row->approver_id);
    }

    public function test_atasan_langsung_menolak_selesai_final_tanpa_naik_tahap(): void
    {
        $requestId = $this->insertSppdRequest($this->employeeId('2018.03.0142'), 'pending');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/sppd/{$requestId}/tolak");

        $response->assertRedirect(route('admin.sppd-approval-queue'));
        $this->assertSame('rejected', DB::table('spd_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pimpinan_kantor_tidak_dapat_memutus_saat_masih_tahap_atasan(): void
    {
        $requestId = $this->insertSppdRequest($this->employeeId('2014.02.0061'), 'pending'); // Nur Aisyah, KP

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->post("/persetujuan/sppd/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('spd_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pimpinan_kantor_memutus_final_pada_tahap_2(): void
    {
        $requestId = $this->insertSppdRequest(
            $this->employeeId('2018.03.0142'), // Siti, KC Mataram
            'pending_pimpinan',
            atasanApproverId: (string) Uuid7::generate(),
        );

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, pimpinan_kantor KC Mataram
            ->post("/persetujuan/sppd/{$requestId}/setujui");

        $response->assertRedirect(route('admin.sppd-approval-queue'));

        $row = DB::table('spd_requests')->where('id', $requestId)->first();
        $this->assertSame('approved', $row->status);
        $this->assertSame($this->employeeId('2015.07.0088'), $row->approver_id);
    }

    public function test_swa_putus_lintas_tahap_ditolak(): void
    {
        $requestId = $this->insertSppdRequest($this->employeeId('2018.03.0142'), 'pending');

        $ahmad = $this->userWithNrp('2015.07.0088');

        $this->actingAs($ahmad)->post("/persetujuan/sppd/{$requestId}/setujui");
        $this->assertSame('pending_pimpinan', DB::table('spd_requests')->where('id', $requestId)->value('status'));

        $response = $this->actingAs($ahmad)->post("/persetujuan/sppd/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending_pimpinan', DB::table('spd_requests')->where('id', $requestId)->value('status'));
    }

    public function test_kategori_apa_pun_mengikuti_alur_2_tahap_yang_sama(): void
    {
        // Jarak Jauh, dulu langsung BANK_WIDE Pejabat SDM — SEKARANG
        // tetap 2 tahap seperti Jarak Pendek, tidak dibedakan lagi.
        $requestId = $this->insertSppdRequest(
            $this->employeeId('2018.03.0142'),
            'pending',
            tripCategory: 'jarak_jauh_keluar_provinsi',
        );

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/sppd/{$requestId}/setujui");

        $response->assertRedirect(route('admin.sppd-approval-queue'));
        $this->assertSame('pending_pimpinan', DB::table('spd_requests')->where('id', $requestId)->value('status'));
    }

    /**
     * Celah ditemukan lewat evaluasi PM/client (2026-08-27) — pola SAMA
     * PERSIS LeaveApprovalQueueScopeTest.
     */
    public function test_penolakan_menyimpan_alasan_dan_mengirim_notifikasi_ke_pemohon(): void
    {
        Notification::fake();

        $requestId = $this->insertSppdRequest($this->employeeId('2018.03.0142'), 'pending');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/sppd/{$requestId}/tolak", ['catatan' => 'Anggaran perjalanan dinas kuartal ini sudah habis.']);

        $response->assertRedirect(route('admin.sppd-approval-queue'));

        $row = DB::table('spd_requests')->where('id', $requestId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Anggaran perjalanan dinas kuartal ini sudah habis.', $row->decision_note);

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === false && $n->reason === 'Anggaran perjalanan dinas kuartal ini sudah habis.',
        );
    }

    public function test_setuju_tahap_atasan_belum_final_tidak_mengirim_notifikasi(): void
    {
        Notification::fake();

        $requestId = $this->insertSppdRequest($this->employeeId('2018.03.0142'), 'pending');

        $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/sppd/{$requestId}/setujui");

        Notification::assertNothingSent();
    }

    public function test_setuju_tahap_pimpinan_final_mengirim_notifikasi_ke_pemohon(): void
    {
        Notification::fake();

        $requestId = $this->insertSppdRequest(
            $this->employeeId('2018.03.0142'),
            'pending_pimpinan',
            atasanApproverId: (string) Uuid7::generate(),
        );

        $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/sppd/{$requestId}/setujui");

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === true,
        );
    }

    public function test_batal_saat_pending_berhasil(): void
    {
        $requestId = $this->insertSppdRequest($this->employeeId('2018.03.0142'), 'pending');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/sppd/{$requestId}/batal");

        $response->assertRedirect();
        $this->assertSame('cancelled', DB::table('spd_requests')->where('id', $requestId)->value('status'));
    }

    public function test_batal_gagal_setelah_tahap_1_diputus(): void
    {
        $requestId = $this->insertSppdRequest(
            $this->employeeId('2018.03.0142'),
            'pending_pimpinan',
            atasanApproverId: (string) Uuid7::generate(),
        );

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/sppd/{$requestId}/batal");

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('pending_pimpinan', DB::table('spd_requests')->where('id', $requestId)->value('status'));
    }

    public function test_riwayat_hanya_menampilkan_pengajuan_milik_sendiri(): void
    {
        $sitiRequestId = $this->insertSppdRequest($this->employeeId('2018.03.0142'), 'pending');
        $ahmadRequestId = $this->insertSppdRequest($this->employeeId('2015.07.0088'), 'pending');

        $sitiNumber = DB::table('spd_requests')->where('id', $sitiRequestId)->value('request_number');
        $ahmadNumber = DB::table('spd_requests')->where('id', $ahmadRequestId)->value('request_number');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/sppd/riwayat');

        $response->assertOk();
        $response->assertSee($sitiNumber);
        $response->assertDontSee($ahmadNumber);
    }

    private function insertSppdRequest(
        string $employeeId,
        string $status,
        ?string $atasanApproverId = null,
        string $tripCategory = 'jarak_pendek',
    ): string {
        $id = (string) Uuid7::generate();

        DB::table('spd_requests')->insert([
            'id' => $id,
            'request_number' => 'SPD/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'trip_category' => $tripCategory,
            'destination' => 'KCP Praya',
            'purpose' => 'Uji',
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-01',
            'total_days' => 1,
            'currency' => 'IDR',
            'uang_makan_cents' => 100_000,
            'uang_saku_cents' => 100_000,
            'status' => $status,
            'atasan_approver_id' => $atasanApproverId,
            'atasan_decided_at' => $atasanApproverId !== null ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function revokeRole(User $user, string $roleName): void
    {
        DB::table('model_has_roles')
            ->where('model_id', $user->getKey())
            ->where('model_type', User::class)
            ->where('role_id', DB::table('roles')->where('name', $roleName)->value('id'))
            ->delete();
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
