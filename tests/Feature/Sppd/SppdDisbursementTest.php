<?php

declare(strict_types=1);

namespace Tests\Feature\Sppd;

use App\Models\User;
use App\Modules\Sppd\Application\SubmitSppdRequest;
use App\Modules\Sppd\Domain\TripCategory;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pencairan SPPD — tahap lanjutan setelah persetujuan, SEKARANG oleh
 * Admin Cabang (hr_admin, kantor sendiri) / Admin HC (hr_approver,
 * BANK_WIDE) menggantikan Treasury (peran itu berhenti dipakai di sini,
 * TIDAK dihapus dari enum). Larangan mencairkan pengajuan yang
 * disetujui sendiri tetap berlaku (§6.3).
 */
final class SppdDisbursementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_hc_dapat_mencairkan_sppd_kantor_mana_pun(): void
    {
        $requestId = $this->submitAndApprove('2018.03.0142', '2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061')) // Nur Aisyah, hr_approver
            ->post("/persetujuan/sppd-pencairan/{$requestId}/cairkan", [
                'disbursement_reference' => 'TRF/2026/09/00123',
            ]);

        $response->assertRedirect(route('admin.sppd-disbursement-queue'));
        $response->assertSessionHas('sukses');

        $spd = DB::table('spd_requests')->where('id', $requestId)->first();
        $this->assertSame('disbursed', $spd->status);
        $this->assertSame('TRF/2026/09/00123', $spd->disbursement_reference);
        $this->assertNotNull($spd->disbursed_at);
        $this->assertSame($this->employeeId('2014.02.0061'), $spd->disbursed_by);
    }

    public function test_admin_cabang_hanya_dapat_mencairkan_sppd_kantornya_sendiri(): void
    {
        // Rina (hr_admin) di KCP Gerung — satu-satunya pegawai di kantor
        // itu pada data contoh, jadi dia sekaligus pemohon SPPD-nya
        // sendiri (bukan pelanggaran §6.3 sebab dia bukan penyetujunya).
        $rinaId = $this->employeeId('2021.05.0302');
        $requestId = $this->submitAndApprove('2021.05.0302', '2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->post("/pegawai/sppd-pencairan/{$requestId}/cairkan", [
                'disbursement_reference' => 'TRF/2026/09/00130',
            ]);

        $response->assertRedirect(route('hr.sppd-disbursement.index'));
        $response->assertSessionHas('sukses');

        $spd = DB::table('spd_requests')->where('id', $requestId)->first();
        $this->assertSame('disbursed', $spd->status);
        $this->assertSame($rinaId, DB::table('emp_employees')->where('id', $rinaId)->value('id'));
    }

    public function test_admin_cabang_tidak_dapat_mencairkan_sppd_kantor_lain(): void
    {
        // Siti (KC Mataram) — BUKAN kantor Rina (KCP Gerung, hr_admin).
        $requestId = $this->submitAndApprove('2018.03.0142', '2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->post("/pegawai/sppd-pencairan/{$requestId}/cairkan", [
                'disbursement_reference' => 'TRF/2026/09/00131',
            ]);

        $response->assertNotFound();
        $this->assertSame('approved', DB::table('spd_requests')->where('id', $requestId)->value('status'));
    }

    public function test_admin_hc_tidak_dapat_mencairkan_sppd_yang_disetujuinya_sendiri(): void
    {
        // Nur Aisyah (hr_approver) JUGA pimpinan_kantor Kantor Pusat di
        // data contoh — sengaja pakai pemohon Kantor Pusat supaya dia
        // sendiri yang menyetujui tahap 2, lalu guard di Application
        // harus tetap menolak dia mencairkannya juga.
        $employeeId = $this->employeeId('2014.02.0061');

        $requestId = $this->submitAndApprove('2014.02.0061', '2014.02.0061');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->post("/persetujuan/sppd-pencairan/{$requestId}/cairkan", [
                'disbursement_reference' => 'TRF/2026/09/00124',
            ]);

        $response->assertRedirect(route('admin.sppd-disbursement-queue'));
        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('setujui sendiri', session('gagal'));

        $this->assertSame('approved', DB::table('spd_requests')->where('id', $requestId)->value('status'));
        $this->assertSame($employeeId, DB::table('spd_requests')->where('id', $requestId)->value('approver_id'));
    }

    public function test_peran_lain_tidak_dapat_mencairkan(): void
    {
        $requestId = $this->submitAndApprove('2018.03.0142', '2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/persetujuan/sppd-pencairan/{$requestId}/cairkan", [
                'disbursement_reference' => 'TRF/2026/09/00125',
            ]);

        $response->assertForbidden();
    }

    public function test_auditor_hanya_melihat_tidak_dapat_mencairkan(): void
    {
        $requestId = $this->submitAndApprove('2018.03.0142', '2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('2020.01.0231'))->get('/persetujuan/sppd-pencairan');
        $response->assertOk();

        $response = $this->actingAs($this->userWithNrp('2020.01.0231'))
            ->post("/persetujuan/sppd-pencairan/{$requestId}/cairkan", [
                'disbursement_reference' => 'TRF/2026/09/00126',
            ]);
        $response->assertForbidden();
    }

    public function test_sppd_pending_belum_muncul_di_antrean_pencairan(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');

        app(SubmitSppdRequest::class)->handle(
            employeeId: $employeeId,
            tripCategory: TripCategory::JarakJauhKeluarProvinsi,
            destination: 'Surabaya',
            purpose: 'Uji',
            startDate: new DateTimeImmutable('2026-09-10'),
            endDate: new DateTimeImmutable('2026-09-10'),
            radiusBand: null,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/persetujuan/sppd-pencairan');

        $response->assertOk();
        $response->assertDontSeeText('Siti Rahmawati');
    }

    private function submitAndApprove(string $pemohonNrp, string $penyetujuNrp): string
    {
        $employeeId = $this->employeeId($pemohonNrp);

        app(SubmitSppdRequest::class)->handle(
            employeeId: $employeeId,
            tripCategory: TripCategory::JarakJauhKeluarProvinsi,
            destination: 'Surabaya',
            purpose: 'Uji',
            startDate: new DateTimeImmutable('2026-09-10'),
            endDate: new DateTimeImmutable('2026-09-10'),
            radiusBand: null,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );

        $requestId = DB::table('spd_requests')->where('employee_id', $employeeId)->value('id');

        DB::table('spd_requests')->where('id', $requestId)->update([
            'status' => 'approved',
            'approver_id' => $this->employeeId($penyetujuNrp),
            'decided_at' => now(),
        ]);

        return $requestId;
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
