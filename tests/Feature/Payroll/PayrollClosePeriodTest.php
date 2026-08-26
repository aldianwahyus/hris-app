<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Domain\Uuid7;
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
 * "Tutup Periode" — meng-approve SEMUA payroll run draft pada satu
 * periode sekaligus (Kantor Pusat + KC + KCP). BEDA dari approve()
 * satu-run: larangan self-approval SENGAJA tidak berlaku di sini
 * (keputusan eksplisit pengguna) — diverifikasi di
 * test_approveAllForPeriod_berhasil_walau_actor_sama_dengan_pembuat,
 * SEDANGKAN approve() satu-run TETAP melarang (regresi, lihat
 * PayrollApprovalScopeTest::test_checker_tidak_dapat_memutus_draf_buatannya_sendiri
 * — tidak diubah oleh perubahan ini, hanya diuji ulang secara eksplisit
 * di sini untuk bukti tambahan bahwa refactor decide() tidak mengubahnya).
 */
final class PayrollClosePeriodTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tutup_periode_meng_approve_seluruh_draf_periode_oleh_actor_yang_sama_dengan_pembuat(): void
    {
        $approver = $this->userWithNrp('2014.02.0061');
        $approverId = $this->employeeId('2014.02.0061');
        $approverOfficeId = DB::table('emp_employees')->where('id', $approverId)->value('office_id');

        $runIdSendiri = app(RunPayrollDraft::class)->handle(
            officeId: $approverOfficeId,
            period: new DateTimeImmutable('2027-05-01'),
            actor: new AuditActor(actorId: $approverId, actorRole: 'hr_approver'),
        );

        $mataramOfficeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('office_id');
        $runIdLain = app(RunPayrollDraft::class)->handle(
            officeId: $mataramOfficeId,
            period: new DateTimeImmutable('2027-05-01'),
            actor: new AuditActor(actorId: $approverId, actorRole: 'hr_approver'),
        );

        $response = $this->actingAs($approver)->post('/persetujuan/payroll/tutup-periode', [
            'period' => '2027-05',
        ]);

        $response->assertRedirect(route('admin.payroll-approval-queue'));
        $response->assertSessionHas('sukses');

        $this->assertSame('approved', DB::table('pay_payroll_runs')->where('id', $runIdSendiri)->value('status'));
        $this->assertSame('approved', DB::table('pay_payroll_runs')->where('id', $runIdLain)->value('status'));
    }

    public function test_tutup_periode_tidak_menyentuh_run_periode_lain_atau_yang_sudah_diputus(): void
    {
        $approver = $this->userWithNrp('2014.02.0061');
        $approverId = $this->employeeId('2014.02.0061');
        $mataramOfficeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('office_id');
        $gerungOfficeId = DB::table('emp_employees')->where('nrp', '2021.05.0302')->value('office_id');

        $runPeriodeLain = app(RunPayrollDraft::class)->handle(
            officeId: $mataramOfficeId,
            period: new DateTimeImmutable('2027-06-01'),
            actor: new AuditActor(actorId: $approverId, actorRole: 'hr_approver'),
        );

        $runSudahDitolak = app(RunPayrollDraft::class)->handle(
            officeId: $gerungOfficeId,
            period: new DateTimeImmutable('2027-07-01'),
            actor: new AuditActor(actorId: $approverId, actorRole: 'hr_approver'),
        );
        // Ditolak oleh actor LAIN (bukan approverId) — reject() satu-run
        // tetap menegakkan larangan self-approval, tidak terpengaruh
        // perubahan approveAllForPeriod().
        app(DecidePayrollRun::class)->reject($runSudahDitolak, new AuditActor(actorId: $this->employeeId('2015.07.0088'), actorRole: 'hr_approver'));

        $this->actingAs($approver)->post('/persetujuan/payroll/tutup-periode', ['period' => '2027-07']);

        $this->assertSame('draft', DB::table('pay_payroll_runs')->where('id', $runPeriodeLain)->value('status'));
        $this->assertSame('rejected', DB::table('pay_payroll_runs')->where('id', $runSudahDitolak)->value('status'));
    }

    public function test_approve_satu_run_tetap_melarang_self_approval_setelah_refactor(): void
    {
        $approverId = $this->employeeId('2014.02.0061');
        $officeId = DB::table('emp_employees')->where('id', $approverId)->value('office_id');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $officeId,
            period: new DateTimeImmutable('2027-08-01'),
            actor: new AuditActor(actorId: $approverId, actorRole: 'hr_approver'),
        );

        $this->expectException(DomainException::class);

        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $approverId, actorRole: 'hr_approver'));
    }

    public function test_hr_admin_ditolak_dari_tutup_periode(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->post('/persetujuan/payroll/tutup-periode', [
            'period' => '2027-05',
        ]);

        $response->assertForbidden();
    }

    public function test_layar_detail_menampilkan_total_bersih_setelah_potongan_dan_tambahan(): void
    {
        $approver = $this->userWithNrp('2014.02.0061');
        $approverId = $this->employeeId('2014.02.0061');
        $mataramOfficeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('office_id');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $mataramOfficeId,
            period: new DateTimeImmutable('2027-09-01'),
            actor: new AuditActor(actorId: $approverId, actorRole: 'hr_approver'),
        );

        $payslip = DB::table('pay_payslips')->where('payroll_run_id', $runId)->first();
        $this->assertNotNull($payslip, 'Perlu minimal satu pegawai eligible pada kantor uji untuk skenario ini.');

        DB::table('pay_payslip_deductions')->insert([
            'id' => (string) Uuid7::generate(),
            'payslip_id' => $payslip->id,
            'deduction_type' => 'lainnya',
            'amount_cents' => 50_000_00,
            'created_by' => $approverId,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->actingAs($approver)->get("/persetujuan/payroll/{$runId}");

        $response->assertOk();
        $expectedNet = $payslip->take_home_partial_cents - 50_000_00;
        $response->assertSeeText('Rp'.number_format($expectedNet / 100, 0, ',', '.'));
    }

    public function test_layar_detail_bisa_diakses_untuk_run_draft_maupun_approved(): void
    {
        $approver = $this->userWithNrp('2014.02.0061');
        $approverId = $this->employeeId('2014.02.0061');
        $officeId = DB::table('emp_employees')->where('id', $approverId)->value('office_id');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $officeId,
            period: new DateTimeImmutable('2027-10-01'),
            actor: new AuditActor(actorId: $approverId, actorRole: 'hr_approver'),
        );

        $this->actingAs($approver)->get("/persetujuan/payroll/{$runId}")->assertOk();

        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $this->employeeId('2015.07.0088'), actorRole: 'hr_approver'));

        $this->actingAs($approver)->get("/persetujuan/payroll/{$runId}")->assertOk();
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
