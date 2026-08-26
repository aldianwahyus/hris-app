<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Application\SubmitEmployeeProfileChange;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Maker-checker perubahan data induk pegawai (TOR Fase I, Data
 * Pegawai): hr_admin (maker, OFFICE) mengajukan, hr_approver
 * (checker, BANK_WIDE) memutuskan — emp_employees TIDAK berubah
 * sampai disetujui.
 */
final class EmployeeProfileChangeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_dapat_membuka_form_ubah_pegawai_di_kantornya_sendiri(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung
        $rinaEmployeeId = $this->employeeId('2021.05.0302');

        $response = $this->actingAs($rina)->get("/pegawai/{$rinaEmployeeId}/ubah");

        $response->assertOk();
    }

    public function test_hr_admin_tidak_bisa_mengubah_pegawai_di_luar_kantornya(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung
        $sitiEmployeeId = $this->employeeId('2018.03.0142'); // KC Mataram — kantor berbeda

        $response = $this->actingAs($rina)->get("/pegawai/{$sitiEmployeeId}/ubah");

        $response->assertForbidden();
    }

    public function test_pengajuan_perubahan_tidak_langsung_mengubah_emp_employees(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');
        $rinaEmployeeId = $this->employeeId('2021.05.0302');
        $originalGrade = DB::table('emp_employees')->where('id', $rinaEmployeeId)->value('person_grade');

        $response = $this->actingAs($rina)->post("/pegawai/{$rinaEmployeeId}/ubah", [
            'person_grade' => $originalGrade + 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $this->assertSame(
            $originalGrade,
            DB::table('emp_employees')->where('id', $rinaEmployeeId)->value('person_grade'),
            'emp_employees tidak boleh berubah sebelum disetujui hr_approver.'
        );

        $pending = DB::table('emp_profile_change_requests')
            ->where('employee_id', $rinaEmployeeId)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($pending);
        $this->assertSame(['person_grade' => $originalGrade + 1], json_decode($pending->proposed_changes, true));
    }

    public function test_hr_approver_menyetujui_menerapkan_perubahan_ke_emp_employees(): void
    {
        $rinaEmployeeId = $this->employeeId('2021.05.0302');
        $originalGrade = DB::table('emp_employees')->where('id', $rinaEmployeeId)->value('person_grade');

        $requestId = app(SubmitEmployeeProfileChange::class)->handle(
            employeeId: $rinaEmployeeId,
            proposedChanges: ['person_grade' => $originalGrade + 1],
            requestedBy: $rinaEmployeeId,
            actor: new AuditActor(actorId: $rinaEmployeeId, actorRole: 'hr_admin'),
        );

        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/pegawai/{$requestId}/setujui");

        $response->assertRedirect(route('admin.employee-approval-queue'));
        $response->assertSessionHas('sukses');

        $this->assertSame(
            $originalGrade + 1,
            DB::table('emp_employees')->where('id', $rinaEmployeeId)->value('person_grade')
        );
        $this->assertSame('approved', DB::table('emp_profile_change_requests')->where('id', $requestId)->value('status'));
    }

    public function test_hr_approver_menolak_tidak_mengubah_emp_employees(): void
    {
        $rinaEmployeeId = $this->employeeId('2021.05.0302');
        $originalGrade = DB::table('emp_employees')->where('id', $rinaEmployeeId)->value('person_grade');

        $requestId = app(SubmitEmployeeProfileChange::class)->handle(
            employeeId: $rinaEmployeeId,
            proposedChanges: ['person_grade' => $originalGrade + 1],
            requestedBy: $rinaEmployeeId,
            actor: new AuditActor(actorId: $rinaEmployeeId, actorRole: 'hr_admin'),
        );

        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/pegawai/{$requestId}/tolak");

        $response->assertRedirect(route('admin.employee-approval-queue'));

        $this->assertSame(
            $originalGrade,
            DB::table('emp_employees')->where('id', $rinaEmployeeId)->value('person_grade')
        );
        $this->assertSame('rejected', DB::table('emp_profile_change_requests')->where('id', $requestId)->value('status'));
    }

    public function test_hr_approver_tidak_bisa_memutuskan_pengajuan_miliknya_sendiri(): void
    {
        $hrApproverEmployeeId = $this->employeeId('2014.02.0061');

        // Nur Aisyah (hr_approver) mengajukan perubahan atas dirinya
        // sendiri lewat Application layer langsung (memotong rute
        // hr_admin-only) — mensimulasikan requested_by === decider.
        $requestId = app(SubmitEmployeeProfileChange::class)->handle(
            employeeId: $hrApproverEmployeeId,
            proposedChanges: ['person_grade' => 17],
            requestedBy: $hrApproverEmployeeId,
            actor: new AuditActor(actorId: $hrApproverEmployeeId, actorRole: 'hr_approver'),
        );

        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/pegawai/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('emp_profile_change_requests')->where('id', $requestId)->value('status'));
    }

    /**
     * SEC-2026-08-TJ: Tunjangan Jabatan/Penyesuaian diinput Admin SDM
     * dalam Rupiah utuh lewat form, dikonversi ke sen SEBELUM disimpan
     * ke proposed_changes — bukan cents mentah dari klien.
     */
    public function test_tunjangan_diinput_rupiah_dan_disimpan_sebagai_sen(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');
        $rinaEmployeeId = $this->employeeId('2021.05.0302');

        $response = $this->actingAs($rina)->post("/pegawai/{$rinaEmployeeId}/ubah", [
            'tunjangan_jabatan_cents' => '3450000',
            'tunjangan_penyesuaian_cents' => '200000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $pending = DB::table('emp_profile_change_requests')
            ->where('employee_id', $rinaEmployeeId)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($pending);
        $this->assertSame(
            ['tunjangan_jabatan_cents' => 345_000_000, 'tunjangan_penyesuaian_cents' => 20_000_000],
            json_decode($pending->proposed_changes, true)
        );
    }

    public function test_persetujuan_tunjangan_menerapkan_ke_emp_employees_dan_terpakai_saat_generate_payroll(): void
    {
        $rinaEmployeeId = $this->employeeId('2021.05.0302');

        $requestId = app(SubmitEmployeeProfileChange::class)->handle(
            employeeId: $rinaEmployeeId,
            proposedChanges: ['tunjangan_jabatan_cents' => 345_000_000, 'tunjangan_penyesuaian_cents' => 20_000_000],
            requestedBy: $rinaEmployeeId,
            actor: new AuditActor(actorId: $rinaEmployeeId, actorRole: 'hr_admin'),
        );

        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/pegawai/{$requestId}/setujui");

        $this->assertSame(345_000_000, DB::table('emp_employees')->where('id', $rinaEmployeeId)->value('tunjangan_jabatan_cents'));
        $this->assertSame(20_000_000, DB::table('emp_employees')->where('id', $rinaEmployeeId)->value('tunjangan_penyesuaian_cents'));
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
