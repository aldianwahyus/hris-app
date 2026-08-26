<?php

declare(strict_types=1);

namespace Tests\Feature\Sppd;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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
