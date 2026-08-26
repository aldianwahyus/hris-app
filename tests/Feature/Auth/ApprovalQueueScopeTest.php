<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Overtime\Application\SubmitOvertimeRequest;
use App\Modules\Overtime\Domain\OvertimeType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsOvertimeAttendance;
use Tests\TestCase;

/**
 * ARCH-001 §6.2 (Role × Organizational Scope × Ownership) dan §6.3
 * (persetujuan tidak boleh atas pengajuan milik sendiri), diperagakan
 * lewat data contoh Wave 1.
 *
 * Lembur SEKARANG 2 TAHAP (koreksi atas DEC-92 versi awal — lihat
 * ApprovalQueueController): Atasan Langsung (status='pending', lingkup
 * OFFICE_TREE) dulu, baru Pimpinan Kantor (status='pending_pimpinan',
 * lingkup OFFICE persis — kepala kantor pemohon). Ahmad Fauzi (Branch
 * Manager KC Mataram) memegang KEDUA peran sekaligus di data contoh —
 * realistis untuk cabang sekecil ini (lihat
 * 2026_08_19_000001_seed_pimpinan_kantor_role) — sehingga guard
 * swa-putus LINTAS TAHAP diuji lewat dia.
 */
final class ApprovalQueueScopeTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_atasan_langsung_melihat_tahap_1_kantor_dan_turunannya_saja(): void
    {
        $this->submitOvertimeFor('2018.03.0142'); // Siti — KC Mataram
        $this->submitOvertimeFor('2020.01.0231'); // Budi — KCP Praya (turunan KC Mataram)
        $this->submitOvertimeFor('2019.09.0177'); // Dewi — KC Selong (TIDAK terkait)

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/persetujuan/lembur');

        $response->assertOk();
        $response->assertSeeText('Siti Rahmawati');
        $response->assertSeeText('Budi Santoso');
        $response->assertDontSeeText('Dewi Lestari');
    }

    public function test_atasan_langsung_setuju_menaikkan_ke_tahap_pimpinan_bukan_final(): void
    {
        $requestId = $this->submitOvertimeFor('2018.03.0142'); // Siti — KC Mataram

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/lembur/{$requestId}/setujui");

        $response->assertRedirect(route('admin.approval-queue'));

        $row = DB::table('ovt_requests')->where('id', $requestId)->first();
        $this->assertSame('pending_pimpinan', $row->status);
        $this->assertSame($this->employeeId('2015.07.0088'), $row->atasan_approver_id);
        $this->assertNotNull($row->atasan_decided_at);
        $this->assertNull($row->approver_id, 'Belum keputusan final — masih menunggu Pimpinan Kantor.');
    }

    public function test_atasan_langsung_menolak_selesai_final_tanpa_naik_tahap(): void
    {
        $requestId = $this->submitOvertimeFor('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/lembur/{$requestId}/tolak");

        $response->assertRedirect(route('admin.approval-queue'));

        $row = DB::table('ovt_requests')->where('id', $requestId)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame($this->employeeId('2015.07.0088'), $row->approver_id);
    }

    public function test_pimpinan_kantor_tidak_dapat_memutus_saat_masih_tahap_atasan(): void
    {
        // Nur Aisyah HANYA pimpinan_kantor (Kantor Pusat) — tidak
        // pernah atasan_langsung di mana pun. Pengajuan buatan sintetis
        // ini SENGAJA masih status 'pending' (tahap 1, belum gilirannya).
        $requestId = $this->insertOvertimeRequest($this->employeeId('2014.02.0061'), 'pending');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->post("/persetujuan/lembur/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('ovt_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pimpinan_kantor_dapat_memutus_final_pada_tahap_2(): void
    {
        // Siti (pemilik, KC Mataram) diputus Ahmad Fauzi (pimpinan_kantor
        // KC Mataram) — orang BERBEDA dari pemilik (bukan Nur Aisyah yang
        // tidak punya bawahan sekantor di data contoh, dan TIDAK BOLEH
        // memutus pengajuan miliknya sendiri).
        $requestId = $this->insertOvertimeRequest(
            $this->employeeId('2018.03.0142'),
            'pending_pimpinan',
            atasanApproverId: (string) Uuid7::generate(), // orang lain yang tidak ada di sistem — cukup beda dari aktor
        );

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/lembur/{$requestId}/setujui");

        $response->assertRedirect(route('admin.approval-queue'));

        $row = DB::table('ovt_requests')->where('id', $requestId)->first();
        $this->assertSame('approved', $row->status);
        $this->assertSame($this->employeeId('2015.07.0088'), $row->approver_id);
    }

    public function test_pimpinan_kantor_hanya_melihat_kantornya_sendiri_bukan_kantor_lain(): void
    {
        // Nur Aisyah — pimpinan_kantor Kantor Pusat SAJA (OFFICE, bukan
        // OFFICE_TREE) — pengajuan tahap 2 kantor lain harus tidak terlihat.
        $ownRequestId = $this->insertOvertimeRequest($this->employeeId('2014.02.0061'), 'pending_pimpinan');
        $otherOfficeRequestId = $this->insertOvertimeRequest($this->employeeId('2019.09.0177'), 'pending_pimpinan'); // Dewi, KC Selong

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/persetujuan/lembur');

        $response->assertOk();
        $response->assertSeeText('Nur Aisyah');
        $response->assertDontSeeText('Dewi Lestari');
    }

    /**
     * Ahmad Fauzi memegang atasan_langsung DAN pimpinan_kantor sekaligus
     * untuk KC Mataram (realistis untuk cabang sekecil ini di data
     * contoh) — guard §6.3 harus tetap mencegah dia memutus KEDUA tahap
     * pengajuan yang SAMA, meski keduanya sah secara peran/lingkup.
     */
    public function test_swa_putus_lintas_tahap_ditolak_walau_lingkup_dan_peran_sah(): void
    {
        $requestId = $this->submitOvertimeFor('2018.03.0142'); // Siti — KC Mataram

        $tahap1 = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/lembur/{$requestId}/setujui");
        $tahap1->assertRedirect(route('admin.approval-queue'));
        $this->assertSame('pending_pimpinan', DB::table('ovt_requests')->where('id', $requestId)->value('status'));

        $tahap2 = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/lembur/{$requestId}/setujui");

        $tahap2->assertForbidden();
        $this->assertSame('pending_pimpinan', DB::table('ovt_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pegawai_biasa_ditolak_dari_antrean_persetujuan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->get('/persetujuan/lembur');

        $response->assertForbidden();
    }

    private function submitOvertimeFor(string $nrp): string
    {
        $user = $this->userWithNrp($nrp);
        $workDate = new DateTimeImmutable('2026-09-05');
        $this->seedOvertimeAttendance($user->employee_id, $workDate, 8.0);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $user->employee_id,
            overtimeType: OvertimeType::CrashProgram,
            workDate: $workDate,
            actor: new AuditActor(actorId: $user->employee_id, actorRole: 'pegawai'),
        );

        return DB::table('ovt_requests')->where('spkl_number', $spklNumber)->value('id');
    }

    private function insertOvertimeRequest(string $employeeId, string $status, ?string $atasanApproverId = null): string
    {
        $id = (string) Uuid7::generate();

        DB::table('ovt_requests')->insert([
            'id' => $id,
            'spkl_number' => 'SPKL/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'overtime_type' => 'crash_program',
            'work_date' => '2026-09-05',
            'status' => $status,
            'atasan_approver_id' => $atasanApproverId,
            'atasan_decided_at' => $atasanApproverId !== null ? now() : null,
            'approval_deadline' => '2026-10-05',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
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
