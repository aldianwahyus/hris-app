<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Payroll\Application\DecidePayrollRun;
use App\Modules\Payroll\Application\RunPayrollDraft;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Slip gaji ESS — lingkup SELF, hanya dari run yang SUDAH DISETUJUI. */
final class PayslipOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_hanya_melihat_slipnya_sendiri_setelah_disetujui(): void
    {
        $siti = $this->userWithNrp('2018.03.0142'); // Officer, KC Mataram
        $officeId = $siti->employee->office_id;

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $officeId,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );

        // Belum disetujui — belum boleh terlihat.
        $response = $this->actingAs($siti)->get('/slip-gaji');
        $response->assertOk();
        $response->assertSeeText('Belum ada slip gaji yang disetujui.');

        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'));

        $response = $this->actingAs($siti)->get('/slip-gaji');
        $response->assertOk();
        $response->assertSeeText('September 2026');
        $response->assertSeeText('menunggu Lampiran III');
    }

    public function test_pegawai_lain_tidak_melihat_angka_slip_yang_bukan_miliknya(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');   // Officer, PG 8 -> Rp2.050.000
        $hendra = $this->userWithNrp('2017.11.0119'); // Satpam, PG 2 -> Rp1.250.000 (kantor sama, KC Mataram)

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );
        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'));

        // Sama-sama tercakup dalam run yang sama (satu kantor), tapi
        // masing-masing HANYA boleh melihat angka Imbalan Kerja miliknya
        // sendiri — bukti isolasi kepemilikan, bukan sekadar "ada datanya".
        $responseSiti = $this->actingAs($siti)->get('/slip-gaji');
        $responseSiti->assertSeeText('Rp2.050.000');
        $responseSiti->assertDontSeeText('Rp1.250.000');

        $responseHendra = $this->actingAs($hendra)->get('/slip-gaji');
        $responseHendra->assertSeeText('Rp1.250.000');
        $responseHendra->assertDontSeeText('Rp2.050.000');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->with('employee')->where('employee_id', $employeeId)->firstOrFail();
    }
}
