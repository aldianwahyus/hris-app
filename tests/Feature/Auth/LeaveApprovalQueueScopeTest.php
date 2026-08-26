<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\LeaveType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cuti SEKARANG 2 TAHAP (koreksi atas Cuti+HrApprover versi awal —
 * lihat LeaveApprovalQueueController): Atasan Langsung dulu, baru
 * Pimpinan Kantor. hr_approver DIHAPUS dari jalur keputusan.
 */
final class LeaveApprovalQueueScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_approver_tidak_lagi_bisa_akses_antrean_cuti(): void
    {
        // Nur Aisyah kebetulan JUGA pimpinan_kantor Kantor Pusat di data
        // demo — cabut peran itu sementara supaya test ini murni menguji
        // hr_approver (bukan tertolong lolos lewat peran lain yang sah).
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->revokeRole($hrApprover, 'pimpinan_kantor');

        $response = $this->actingAs($hrApprover)->get('/persetujuan/cuti');

        $response->assertForbidden();
    }

    public function test_atasan_langsung_setuju_menaikkan_ke_tahap_pimpinan_bukan_final(): void
    {
        $requestId = $this->insertLeaveRequest($this->employeeId('2018.03.0142'), 'pending'); // Siti, KC Mataram

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/cuti/{$requestId}/setujui");

        $response->assertRedirect(route('admin.leave-approval-queue'));

        $row = DB::table('leave_requests')->where('id', $requestId)->first();
        $this->assertSame('pending_pimpinan', $row->status);
        $this->assertSame($this->employeeId('2015.07.0088'), $row->atasan_approver_id);
        $this->assertNull($row->approver_id);
    }

    public function test_atasan_langsung_menolak_selesai_final_tanpa_naik_tahap(): void
    {
        $requestId = $this->insertLeaveRequest($this->employeeId('2018.03.0142'), 'pending');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/cuti/{$requestId}/tolak");

        $response->assertRedirect(route('admin.leave-approval-queue'));
        $this->assertSame('rejected', DB::table('leave_requests')->where('id', $requestId)->value('status'));
    }

    /**
     * Bug ditemukan lewat audit kode, diperbaiki hari ini:
     * SubmitLeaveRequest mendebit leave_balances.used_days SAAT
     * pengajuan (bukan saat disetujui) — sebelumnya, penolakan TIDAK
     * PERNAH mengembalikan hari itu, menghanguskan jatah cuti pegawai
     * secara permanen untuk pengajuan yang tidak pernah disetujui.
     */
    public function test_penolakan_tahap_atasan_mengembalikan_hari_cuti_yang_terpotong(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti, KC Mataram
        $usedDaysBefore = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');

        // 2026-09-01 (Selasa) s.d. 2026-09-07 (Senin) = 5 hari kerja murni.
        $requestNumber = app(SubmitLeaveRequest::class)->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-07'),
            reason: null,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );
        $requestId = DB::table('leave_requests')->where('request_number', $requestNumber)->value('id');

        $usedDaysAfterSubmit = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');
        $this->assertEquals($usedDaysBefore + 5.0, $usedDaysAfterSubmit);

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad Fauzi — atasan_langsung KC Mataram
            ->post("/persetujuan/cuti/{$requestId}/tolak");

        $response->assertRedirect(route('admin.leave-approval-queue'));
        $this->assertSame('rejected', DB::table('leave_requests')->where('id', $requestId)->value('status'));

        $usedDaysAfterReject = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');
        $this->assertEquals($usedDaysBefore, $usedDaysAfterReject, 'Hari cuti harus dikembalikan penuh saat pengajuan ditolak.');
    }

    public function test_pimpinan_kantor_tidak_dapat_memutus_saat_masih_tahap_atasan(): void
    {
        $requestId = $this->insertLeaveRequest($this->employeeId('2014.02.0061'), 'pending'); // Nur Aisyah, KP — pimpinan_kantor KP

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->post("/persetujuan/cuti/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('leave_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pimpinan_kantor_memutus_final_pada_tahap_2(): void
    {
        $requestId = $this->insertLeaveRequest(
            $this->employeeId('2018.03.0142'), // Siti, KC Mataram
            'pending_pimpinan',
            atasanApproverId: (string) Uuid7::generate(),
        );

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, pimpinan_kantor KC Mataram
            ->post("/persetujuan/cuti/{$requestId}/setujui");

        $response->assertRedirect(route('admin.leave-approval-queue'));

        $row = DB::table('leave_requests')->where('id', $requestId)->first();
        $this->assertSame('approved', $row->status);
        $this->assertSame($this->employeeId('2015.07.0088'), $row->approver_id);
    }

    public function test_swa_putus_lintas_tahap_ditolak(): void
    {
        $requestId = $this->insertLeaveRequest($this->employeeId('2018.03.0142'), 'pending');

        $ahmad = $this->userWithNrp('2015.07.0088');

        $this->actingAs($ahmad)->post("/persetujuan/cuti/{$requestId}/setujui");
        $this->assertSame('pending_pimpinan', DB::table('leave_requests')->where('id', $requestId)->value('status'));

        $response = $this->actingAs($ahmad)->post("/persetujuan/cuti/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending_pimpinan', DB::table('leave_requests')->where('id', $requestId)->value('status'));
    }

    private function insertLeaveRequest(string $employeeId, string $status, ?string $atasanApproverId = null): string
    {
        $id = (string) Uuid7::generate();

        DB::table('leave_requests')->insert([
            'id' => $id,
            'request_number' => 'CT/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'leave_type' => 'CT',
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-05',
            'total_days' => 5,
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
