<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Payroll\Application\DecidePayrollRun;
use App\Modules\Payroll\Application\RunPayrollDraft;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pejabat SDM (checker, lingkup BANK_WIDE) menyetujui/menolak draf
 * dari kantor mana pun — TIDAK boleh memutus buatan sendiri (§6.3).
 */
final class PayrollApprovalScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_approver_melihat_draf_dari_kantor_manapun(): void
    {
        $this->createDraft('2021.05.0302'); // Rina Marlina, KCP Gerung
        $this->createDraft('2015.07.0088'); // Ahmad Fauzi, KC Mataram (dirinya sendiri bukan maker payroll, tapi kantornya jadi ruang lingkup run)

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/persetujuan/payroll');

        $response->assertOk();
        $response->assertSeeText('KCP Gerung');
        $response->assertSeeText('KC Mataram');
    }

    public function test_hr_approver_dapat_menyetujui_draf_kantor_lain(): void
    {
        $runId = $this->createDraft('2021.05.0302');
        $approver = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($approver)->post("/persetujuan/payroll/{$runId}/setujui");

        $response->assertRedirect(route('admin.payroll-approval-queue'));
        $this->assertSame('approved', DB::table('pay_payroll_runs')->where('id', $runId)->value('status'));

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'payroll_run')->where('auditable_id', $runId)->where('action', 'approved')->first();
        $this->assertNotNull($audit);
    }

    public function test_checker_tidak_dapat_memutus_draf_buatannya_sendiri(): void
    {
        $nurAisyahId = DB::table('emp_employees')->where('nrp', '2014.02.0061')->value('id');
        $officeId = DB::table('emp_employees')->where('nrp', '2014.02.0061')->value('office_id');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $officeId,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $nurAisyahId, actorRole: 'hr_approver'),
        );

        $this->expectException(DomainException::class);

        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $nurAisyahId, actorRole: 'hr_approver'));
    }

    public function test_peran_lain_ditolak_dari_persetujuan_payroll(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/persetujuan/payroll');

        $response->assertForbidden();
    }

    private function createDraft(string $makerNrp): string
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $makerNrp)->value('id');
        $officeId = DB::table('emp_employees')->where('nrp', $makerNrp)->value('office_id');

        return app(RunPayrollDraft::class)->handle(
            officeId: $officeId,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $employeeId, actorRole: 'hr_admin'),
        );
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
