<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Payroll\Application\RunPayrollDraft;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Generate massal payroll (hr_approver, BANK_WIDE) — melengkapi
 * RunPayrollDraft per-kantor (hr_admin, OFFICE-scoped) yang tetap ada.
 */
final class PayrollBulkGenerationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_approver_generate_massal_membuat_draf_di_beberapa_kantor_sekaligus(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post('/persetujuan/payroll/generate-massal', [
            'period' => '2027-01',
        ]);

        $response->assertRedirect(route('admin.payroll-approval-queue'));
        $response->assertSessionHas('sukses');

        $runCount = DB::table('pay_payroll_runs')->where('period', '2027-01-01')->count();

        $this->assertGreaterThan(1, $runCount, 'Harus membuat draf di lebih dari satu kantor dalam satu aksi.');
    }

    public function test_kantor_yang_sudah_punya_draf_dilewati_tanpa_menggagalkan_kantor_lain(): void
    {
        $mataramOfficeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('office_id');

        // Kantor Mataram sudah punya draf periode ini SEBELUM generate massal.
        app(RunPayrollDraft::class)->handle(
            officeId: $mataramOfficeId,
            period: new DateTimeImmutable('2027-02-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );
        $runCountBefore = DB::table('pay_payroll_runs')->where('period', '2027-02-01')->count();

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post('/persetujuan/payroll/generate-massal', [
            'period' => '2027-02',
        ]);

        $response->assertSessionHas('sukses');
        $this->assertStringContainsString('Dilewati', session('sukses'));

        $runCountAfter = DB::table('pay_payroll_runs')->where('period', '2027-02-01')->count();
        $this->assertGreaterThan($runCountBefore, $runCountAfter, 'Kantor lain tetap harus digenerate meski satu kantor dilewati.');

        $this->assertSame(
            1,
            DB::table('pay_payroll_runs')->where('period', '2027-02-01')->where('office_id', $mataramOfficeId)->count(),
            'Kantor yang sudah punya draf tidak boleh punya draf ganda.'
        );
    }

    public function test_hr_admin_ditolak_dari_generate_massal(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->post('/persetujuan/payroll/generate-massal', [
            'period' => '2027-03',
        ]);

        $response->assertForbidden();
    }

    /**
     * Regresi: periode sebelum iuran pensiun/THT berlaku (13 Januari
     * 2026, SK BPP/137/03/64/2026) dulu menghasilkan halaman 500 mentah
     * (ParameterNotFoundException tidak tertangkap) alih-alih pesan
     * "gagal" yang bisa dipahami pengguna.
     */
    public function test_periode_sebelum_parameter_iuran_berlaku_menampilkan_pesan_gagal_bukan_500(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post('/persetujuan/payroll/generate-massal', [
            'period' => '2026-01',
        ]);

        $response->assertRedirect(route('admin.payroll-approval-queue'));
        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('CONTRIB_PENSION_EMPLOYEE_PCT', session('gagal'));

        $this->assertSame(
            0,
            DB::table('pay_payroll_runs')->where('period', '2026-01-01')->count(),
            'Tidak boleh ada draf payroll yang terbentuk untuk periode yang gagal.'
        );
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
