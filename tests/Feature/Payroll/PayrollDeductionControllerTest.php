<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Payroll\Application\DecidePayrollRun;
use App\Modules\Payroll\Application\RunPayrollDraft;
use App\Modules\Payroll\Domain\DeductionType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Input potongan gaji (hr_admin, maker) SELAMA payroll run kantornya
 * masih draft — wewenang SEMPIT, berbeda dari generate (tetap terlarang
 * total, lihat PayrollDraftScopeTest).
 */
final class PayrollDeductionControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_cabang_dapat_menambah_dan_menghapus_potongan_manual(): void
    {
        $runId = $this->draftRunFor('KCP-GRG');
        $payslipId = DB::table('pay_payslips')->where('payroll_run_id', $runId)->value('id');

        $store = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->post("/pegawai/payroll/{$runId}/pegawai/{$payslipId}/potongan", [
                'deduction_type' => 'kasbon_pinjaman',
                'amount' => 500000,
                'note' => 'Cicilan ke-3',
            ]);

        $store->assertRedirect(route('hr.payroll-deduction.show', $runId));
        $store->assertSessionHas('sukses');

        $deduction = DB::table('pay_payslip_deductions')->where('payslip_id', $payslipId)->first();
        $this->assertNotNull($deduction);
        $this->assertSame(50000000, $deduction->amount_cents);
        $this->assertSame('Cicilan ke-3', $deduction->note);

        $destroy = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->delete("/pegawai/payroll/{$runId}/pegawai/{$payslipId}/potongan/{$deduction->id}");

        $destroy->assertRedirect(route('hr.payroll-deduction.show', $runId));
        $this->assertSame(0, DB::table('pay_payslip_deductions')->where('payslip_id', $payslipId)->count());
    }

    /**
     * Fase II (format KITIR) — tambahan penghasilan ad-hoc (Tunj.
     * Pengobatan dst.), cermin persis alur potongan di atas tapi lewat
     * RecordPayslipAddition/RemovePayslipAddition dan tabel
     * pay_payslip_additions.
     */
    public function test_admin_cabang_dapat_menambah_dan_menghapus_tambahan_penghasilan(): void
    {
        $runId = $this->draftRunFor('KCP-GRG');
        $payslipId = DB::table('pay_payslips')->where('payroll_run_id', $runId)->value('id');

        $store = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->post("/pegawai/payroll/{$runId}/pegawai/{$payslipId}/tambahan", [
                'addition_type' => 'tunjangan_pengobatan',
                'amount' => 150000,
                'note' => 'Rawat jalan',
            ]);

        $store->assertRedirect(route('hr.payroll-deduction.show', $runId));
        $store->assertSessionHas('sukses');

        $addition = DB::table('pay_payslip_additions')->where('payslip_id', $payslipId)->first();
        $this->assertNotNull($addition);
        $this->assertSame('tunjangan_pengobatan', $addition->addition_type);
        $this->assertSame(15000000, $addition->amount_cents);
        $this->assertSame('Rawat jalan', $addition->note);

        $destroy = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->delete("/pegawai/payroll/{$runId}/pegawai/{$payslipId}/tambahan/{$addition->id}");

        $destroy->assertRedirect(route('hr.payroll-deduction.show', $runId));
        $this->assertSame(0, DB::table('pay_payslip_additions')->where('payslip_id', $payslipId)->count());
    }

    public function test_admin_cabang_ditolak_404_dari_run_kantor_lain(): void
    {
        // Rina (hr_admin, KCP-GRG) mencoba mengakses draf milik KC
        // Mataram — kepemilikan office_id-lah yang menggerbangi, bukan
        // sekadar status draft.
        $otherOfficeRunId = $this->draftRunFor('KC-MTR');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get("/pegawai/payroll/{$otherOfficeRunId}");

        $response->assertNotFound();
    }

    public function test_akses_tertutup_total_setelah_run_disetujui(): void
    {
        $runId = $this->draftRunFor('KCP-GRG');

        app(DecidePayrollRun::class)->approve($runId, new AuditActor(
            actorId: $this->employeeId('2014.02.0061'),
            actorRole: 'hr_approver',
        ));

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get("/pegawai/payroll/{$runId}");
        $response->assertNotFound();
    }

    public function test_reopen_oleh_hr_approver_mengembalikan_akses_admin_cabang(): void
    {
        $runId = $this->draftRunFor('KCP-GRG');

        app(DecidePayrollRun::class)->approve($runId, new AuditActor(
            actorId: $this->employeeId('2014.02.0061'),
            actorRole: 'hr_approver',
        ));

        $reopen = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->post("/persetujuan/payroll/{$runId}/buka-kembali");
        $reopen->assertRedirect(route('admin.payroll-approval-queue'));
        $reopen->assertSessionHas('sukses');

        $this->assertSame('draft', DB::table('pay_payroll_runs')->where('id', $runId)->value('status'));

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get("/pegawai/payroll/{$runId}");
        $response->assertOk();
    }

    public function test_impor_csv_menerapkan_potongan_dan_melewati_baris_nrp_tak_dikenal(): void
    {
        $runId = $this->draftRunFor('KCP-GRG');
        $nrp = '2021.05.0302';

        // Header dibangun dari DeductionType::cases() (bukan ditulis
        // tangan) — mapHeader() di ImportPayslipDeductions mewajibkan
        // SEMUA kolom jenis potongan ada (pola sama template yang
        // sungguhan diunduh dari GeneratePayslipDeductionTemplate),
        // supaya penambahan kategori baru di Fase II tidak diam-diam
        // membuat fixture CSV tes ini basi.
        $header = ['nrp', 'nama', 'penghasilan_sebelum_potongan'];
        $baris1 = [$nrp, 'Rina Marlina', '3000000'];
        $baris2 = ['9999.99.9999', 'NRP Tidak Ada', '0'];

        foreach (DeductionType::cases() as $type) {
            $header[] = 'potongan_'.$type->value;
            $header[] = 'catatan_'.$type->value;

            if ($type === DeductionType::KasbonPinjaman) {
                $baris1[] = '250000';
                $baris1[] = 'Kasbon Agustus';
            } else {
                $baris1[] = '';
                $baris1[] = '';
            }

            $baris2[] = '';
            $baris2[] = '';
        }

        $csv = implode(',', $header)."\n".implode(',', $baris1)."\n".implode(',', $baris2)."\n";

        $file = UploadedFile::fake()->createWithContent('potongan.csv', $csv);

        $response = $this->actingAs($this->userWithNrp($nrp))
            ->post("/pegawai/payroll/{$runId}/impor", ['berkas' => $file]);

        $response->assertRedirect(route('hr.payroll-deduction.show', $runId));
        $response->assertSessionHas('sukses');
        $this->assertStringContainsString('1 baris dilewati', session('sukses'));

        $payslipId = DB::table('pay_payslips as s')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->where('s.payroll_run_id', $runId)->where('e.nrp', $nrp)->value('s.id');

        $deduction = DB::table('pay_payslip_deductions')->where('payslip_id', $payslipId)->first();
        $this->assertNotNull($deduction);
        $this->assertSame('kasbon_pinjaman', $deduction->deduction_type);
        $this->assertSame(25000000, $deduction->amount_cents);
    }

    private function draftRunFor(string $officeCode): string
    {
        $officeId = DB::table('md_offices')->where('code', $officeCode)->value('id');

        // "Pembuat" (created_by) SENGAJA bukan Nur Aisyah (satu-satunya
        // hr_approver di data contoh) — DecidePayrollRun::approve()
        // menolak checker yang sama dengan maker (§6.3), dan test ini
        // butuh Nur Aisyah bisa approve/reopen run yang dibuat.
        return app(RunPayrollDraft::class)->handle(
            officeId: $officeId,
            period: new DateTimeImmutable('2027-05-01'),
            actor: new AuditActor(actorId: $this->employeeId('2015.07.0088'), actorRole: 'hr_approver'),
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
