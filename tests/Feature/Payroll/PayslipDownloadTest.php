<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Payroll\Application\DecidePayrollRun;
use App\Modules\Payroll\Application\RunPayrollDraft;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Unduh slip gaji sebagai PDF — lingkup SELF yang sama dengan index(). */
final class PayslipDownloadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_mengunduh_slip_miliknya_sendiri_sebagai_pdf(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );
        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'));

        $slipId = DB::table('pay_payslips')->where('employee_id', $siti->employee_id)->value('id');

        $response = $this->actingAs($siti)->get("/slip-gaji/{$slipId}/unduh");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    /**
     * dompdf mengompresi isi PDF (FlateDecode) sehingga tidak praktis
     * mencari teks langsung di respons biner — di sini merender
     * template yang SAMA (payroll.payslip-pdf) langsung ke HTML dengan
     * data query yang SAMA seperti PayslipController::download(),
     * memverifikasi blok tanda tangan tanpa bergantung pada dompdf.
     */
    public function test_pdf_memuat_nama_dan_jabatan_penyetuju_sebagai_tanda_tangan(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );
        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'));

        $slip = DB::table('pay_payslips as s')
            ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->join('md_positions as op', 'op.id', '=', 'e.position_id')
            ->join('md_offices as oo', 'oo.id', '=', 'e.office_id')
            ->join('emp_employees as approver', 'approver.id', '=', 'r.approved_by')
            ->join('md_positions as ap', 'ap.id', '=', 'approver.position_id')
            ->where('s.employee_id', $siti->employee_id)
            ->select(
                's.*', 'r.period', 'e.full_name', 'e.nrp', 'e.nomor_simpeda',
                'op.name as jabatan_name', 'oo.name as unit_kerja_name',
                'approver.full_name as approver_name', 'ap.name as approver_position',
            )
            ->first();

        $html = view('payroll.payslip-pdf', [
            's' => $slip,
            'deductionsByType' => collect(),
            'additionsByType' => collect(),
        ])->render();

        $this->assertStringContainsString('Divisi SDI', $html);
        $this->assertStringContainsString('Nur Aisyah', $html);
        $this->assertStringContainsString('Division Head', $html);
    }

    /**
     * Fase II (format KITIR) — potongan/tambahan ad-hoc TIDAK PERNAH
     * memutasi take_home_partial_cents (lihat RecordPayslipDeduction/
     * RecordPayslipAddition), jadi JUMLAH PENGHASILAN/POTONGAN/THP di
     * PDF WAJIB dihitung dari kombinasi kolom tetap + baris-baris ini —
     * tes ini memverifikasi aritmetikanya PERSIS, termasuk penomoran
     * kategori berulang (2x Kasbon/Pinjaman).
     */
    public function test_pdf_menghitung_jumlah_dan_thp_dari_potongan_dan_tambahan_ad_hoc(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        DB::table('emp_employees')->where('id', $siti->employee_id)->update(['marital_status' => null]);

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );
        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'));

        $slipId = DB::table('pay_payslips')->where('employee_id', $siti->employee_id)->value('id');
        $hrAdminId = $this->employeeId('2021.05.0302');
        $now = now();

        DB::table('pay_payslip_deductions')->insert([
            ['id' => (string) Uuid7::generate(), 'payslip_id' => $slipId, 'deduction_type' => 'kasbon_pinjaman', 'amount_cents' => 500_000_00, 'note' => null, 'created_by' => $hrAdminId, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => (string) Uuid7::generate(), 'payslip_id' => $slipId, 'deduction_type' => 'kasbon_pinjaman', 'amount_cents' => 200_000_00, 'note' => null, 'created_by' => $hrAdminId, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => (string) Uuid7::generate(), 'payslip_id' => $slipId, 'deduction_type' => 'astek', 'amount_cents' => 150_000_00, 'note' => null, 'created_by' => $hrAdminId, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
        ]);
        DB::table('pay_payslip_additions')->insert([
            ['id' => (string) Uuid7::generate(), 'payslip_id' => $slipId, 'addition_type' => 'tunjangan_pengobatan', 'amount_cents' => 100_000_00, 'note' => null, 'created_by' => $hrAdminId, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
        ]);

        $slip = DB::table('pay_payslips as s')
            ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->join('md_positions as op', 'op.id', '=', 'e.position_id')
            ->join('md_offices as oo', 'oo.id', '=', 'e.office_id')
            ->join('emp_employees as approver', 'approver.id', '=', 'r.approved_by')
            ->join('md_positions as ap', 'ap.id', '=', 'approver.position_id')
            ->where('s.id', $slipId)
            ->select(
                's.*', 'r.period', 'e.full_name', 'e.nrp', 'e.nomor_simpeda',
                'op.name as jabatan_name', 'oo.name as unit_kerja_name',
                'approver.full_name as approver_name', 'ap.name as approver_position',
            )
            ->first();
        $deductionsByType = DB::table('pay_payslip_deductions')->where('payslip_id', $slipId)->orderBy('created_at')->get()->groupBy('deduction_type');
        $additionsByType = DB::table('pay_payslip_additions')->where('payslip_id', $slipId)->orderBy('created_at')->get()->groupBy('addition_type');

        $expectedPenghasilan = $slip->imbalan_kerja_cents + $slip->tunjangan_jabatan_cents
            + 100_000_00 + $slip->tunjangan_penyesuaian_cents + 0; // pph21 null, tunjangan pajak = 0
        $expectedPotongan = $slip->iuran_pensiun_pegawai_cents + $slip->iuran_tht_pegawai_cents
            + 500_000_00 + 200_000_00 + 150_000_00 + 0;
        $expectedThp = $expectedPenghasilan - $expectedPotongan;

        $html = view('payroll.payslip-pdf', [
            's' => $slip,
            'deductionsByType' => $deductionsByType,
            'additionsByType' => $additionsByType,
        ])->render();

        $this->assertStringContainsString('Kasbon/Pinjaman 1', $html);
        $this->assertStringContainsString('Kasbon/Pinjaman 2', $html);
        $rp = fn (int $cents) => 'Rp'.number_format($cents / 100, 0, ',', '.');
        $this->assertStringContainsString($rp($expectedPenghasilan), $html);
        $this->assertStringContainsString($rp($expectedPotongan), $html);
        $this->assertStringContainsString($rp($expectedThp), $html);
    }

    /**
     * SEC-2026-08-TJ: Tunjangan Jabatan/Penyesuaian pada data induk
     * pegawai ikut tersalin ke payslip saat generate DAN menambah
     * take_home_partial_cents (bukan hanya tersimpan tanpa berpengaruh).
     */
    public function test_tunjangan_jabatan_dan_penyesuaian_ikut_masuk_payslip_dan_take_home(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        DB::table('emp_employees')->where('id', $siti->employee_id)->update([
            'tunjangan_jabatan_cents' => 345_000_000,
            'tunjangan_penyesuaian_cents' => 20_000_000,
            // PTKP dikosongkan SENGAJA — isolasi test ini dari PPh21
            // (fitur terpisah, lihat test_pph21_sementara_dihitung...
            // di bawah) supaya rumus take_home di sini tetap sederhana.
            'marital_status' => null,
        ]);

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );

        $slip = DB::table('pay_payslips')->where('employee_id', $siti->employee_id)->where('payroll_run_id', $runId)->first();

        $this->assertSame(345_000_000, $slip->tunjangan_jabatan_cents);
        $this->assertSame(20_000_000, $slip->tunjangan_penyesuaian_cents);
        $this->assertSame(
            $slip->imbalan_kerja_cents + 345_000_000 + 20_000_000 - $slip->iuran_pensiun_pegawai_cents - $slip->iuran_tht_pegawai_cents,
            $slip->take_home_partial_cents,
        );
        $this->assertNotContains('Tunjangan Jabatan (menunggu Lampiran III)', json_decode($slip->pending_components, true));
    }

    /**
     * PPh21 dihitung SEMENTARA (provisional, atas persetujuan eksplisit
     * pengguna) begitu PTKP pegawai lengkap — basis TER wajib Gaji
     * BRUTO (Imbalan Kerja + Tunjangan Jabatan + Penyesuaian, SEBELUM
     * iuran dipotong), bukan takeHomePartial yang sudah dikurangi iuran.
     */
    public function test_pph21_sementara_dihitung_saat_ptkp_lengkap_dan_dikurangkan_dari_take_home(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        DB::table('emp_employees')->where('id', $siti->employee_id)->update([
            'tunjangan_jabatan_cents' => 0,
            'tunjangan_penyesuaian_cents' => 0,
            'marital_status' => 'belum menikah',
            'tanggungan' => 0,
        ]);

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );

        $slip = DB::table('pay_payslips')->where('employee_id', $siti->employee_id)->where('payroll_run_id', $runId)->first();

        $this->assertSame('A', $slip->pph21_golongan); // belum menikah, 0 tanggungan
        $this->assertNotNull($slip->pph21_cents);
        $this->assertSame(
            $slip->imbalan_kerja_cents - $slip->iuran_pensiun_pegawai_cents - $slip->iuran_tht_pegawai_cents - $slip->pph21_cents,
            $slip->take_home_partial_cents,
        );

        $pending = json_decode($slip->pending_components, true);
        $this->assertContains(
            'PPh 21 SEMENTARA (provisional) — dihitung dari Gaji Kotor yang BELUM mencakup Tunjangan Kinerja/Kemahalan. WAJIB dikoreksi ulang setelah Lampiran III lengkap, jangan dianggap final.',
            $pending
        );
    }

    public function test_pph21_tidak_dihitung_saat_ptkp_belum_lengkap(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        DB::table('emp_employees')->where('id', $siti->employee_id)->update(['marital_status' => null]);

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );

        $slip = DB::table('pay_payslips')->where('employee_id', $siti->employee_id)->where('payroll_run_id', $runId)->first();

        $this->assertNull($slip->pph21_golongan);
        $this->assertNull($slip->pph21_cents);
        $this->assertContains(
            'PPh 21 belum dihitung untuk pegawai ini — data PTKP (status kawin/jumlah tanggungan) belum lengkap pada data induk pegawai.',
            json_decode($slip->pending_components, true)
        );
    }

    public function test_slip_dari_run_belum_disetujui_tidak_bisa_diunduh(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );

        $slipId = DB::table('pay_payslips')->where('employee_id', $siti->employee_id)->value('id');

        $response = $this->actingAs($siti)->get("/slip-gaji/{$slipId}/unduh");

        $response->assertNotFound();
    }

    public function test_pegawai_lain_tidak_bisa_mengunduh_slip_yang_bukan_miliknya(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $hendra = $this->userWithNrp('2017.11.0119');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: $siti->employee->office_id,
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );
        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'));

        $slipId = DB::table('pay_payslips')->where('employee_id', $siti->employee_id)->value('id');

        $response = $this->actingAs($hendra)->get("/slip-gaji/{$slipId}/unduh");

        $response->assertNotFound();
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
