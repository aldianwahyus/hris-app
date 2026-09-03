<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

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
 * Rekap Penghasilan — total gaji+lembur+SPPD+bekal cuti per pegawai per
 * bulan. Lingkup SAMA PERSIS OvertimeRecapTest (hr_admin: kantornya
 * sendiri, hr_approver: seluruh bank).
 */
final class IncomeRecapTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_hr_admin_hanya_melihat_rekap_kantornya_sendiri(): void
    {
        // Rina Marlina (hr_admin) — KCP Gerung.
        $this->insertApprovedPayroll('2021.05.0302', '2026-09-01', 5_000_000_00);
        // Siti — kantor berbeda.
        $this->insertApprovedPayroll('2018.03.0142', '2026-09-01', 6_000_000_00);

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/penghasilan?bulan=2026-09');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertDontSeeText('Siti Rahmawati');
    }

    public function test_hr_approver_melihat_rekap_seluruh_bank(): void
    {
        $this->insertApprovedPayroll('2021.05.0302', '2026-09-01', 5_000_000_00);
        $this->insertApprovedPayroll('2018.03.0142', '2026-09-01', 6_000_000_00);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061')) // hr_approver
            ->get('/pegawai/penghasilan?bulan=2026-09');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertSeeText('Siti Rahmawati');
    }

    public function test_menjumlah_gaji_lembur_sppd_dan_bekal_cuti_dengan_benar(): void
    {
        $nrp = '2018.03.0142'; // Siti, KC Mataram
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        // Gaji bersih: take_home_partial 5.000.000 + tambahan 200.000 - potongan 300.000 = 4.900.000
        $payslipId = $this->insertApprovedPayroll($nrp, '2026-09-01', 5_000_000_00);
        DB::table('pay_payslip_additions')->insert([
            'id' => (string) Uuid7::generate(), 'payslip_id' => $payslipId, 'addition_type' => 'bonus',
            'amount_cents' => 200_000_00, 'note' => null, 'created_by' => $employeeId,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);
        DB::table('pay_payslip_deductions')->insert([
            'id' => (string) Uuid7::generate(), 'payslip_id' => $payslipId, 'deduction_type' => 'kasbon_pinjaman',
            'amount_cents' => 300_000_00, 'note' => null, 'created_by' => $employeeId,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        // Lembur disetujui bulan yang sama: 250.000
        $this->seedOvertimeAttendance($employeeId, new DateTimeImmutable('2026-09-02'), 2.0);
        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: new DateTimeImmutable('2026-09-02'),
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );
        DB::table('ovt_requests')->where('spkl_number', $spklNumber)->update([
            'status' => 'approved', 'approver_id' => $employeeId, 'decided_at' => now(), 'amount_cents' => 250_000_00,
        ]);

        // SPPD disetujui bulan yang sama: uang makan 100.000 + uang saku 150.000 = 250.000
        DB::table('spd_requests')->insert([
            'id' => (string) Uuid7::generate(), 'request_number' => 'SPD/TEST/'.uniqid(),
            'employee_id' => $employeeId, 'trip_category' => 'jarak_pendek', 'destination' => 'KCP Praya',
            'purpose' => 'Uji', 'start_date' => '2026-09-05', 'end_date' => '2026-09-05', 'total_days' => 1,
            'currency' => 'IDR', 'uang_makan_cents' => 100_000_00, 'uang_saku_cents' => 150_000_00,
            'status' => 'approved', 'approver_id' => $employeeId, 'decided_at' => now(),
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        // Bekal Cuti dicairkan bulan yang sama: 1.000.000
        $leaveRequestId = (string) Uuid7::generate();
        DB::table('leave_requests')->insert([
            'id' => $leaveRequestId, 'request_number' => 'CT/TEST/'.uniqid(), 'employee_id' => $employeeId,
            'leave_type' => 'CT', 'start_date' => '2026-09-08', 'end_date' => '2026-09-08', 'total_days' => 1,
            'status' => 'approved', 'approver_id' => $employeeId, 'decided_at' => now(),
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);
        DB::table('pay_bekal_cuti_disbursements')->insert([
            'id' => (string) Uuid7::generate(), 'employee_id' => $employeeId, 'leave_request_id' => $leaveRequestId,
            'year' => 2026, 'amount_cents' => 1_000_000_00, 'status' => 'disbursed',
            'disbursed_by' => $employeeId, 'disbursed_at' => '2026-09-10',
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061')) // hr_approver
            ->get('/pegawai/penghasilan?bulan=2026-09');

        $response->assertOk();
        // Gaji 4.900.000 + Lembur 250.000 + SPPD 250.000 + Bekal Cuti 1.000.000 = 6.400.000
        $response->assertSeeText('Rp6.400.000');
    }

    public function test_payroll_run_berstatus_draft_tidak_masuk_rekap(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $this->insertPayroll('2018.03.0142', '2026-09-01', 5_000_000_00, 'draft');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->get('/pegawai/penghasilan?bulan=2026-09');

        $response->assertOk();
        $response->assertDontSeeText('Rp5.000.000');
    }

    private function insertApprovedPayroll(string $nrp, string $period, int $takeHomeCents): string
    {
        return $this->insertPayroll($nrp, $period, $takeHomeCents, 'approved');
    }

    private function insertPayroll(string $nrp, string $period, int $takeHomeCents, string $status): string
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');
        $officeId = DB::table('emp_employees')->where('id', $employeeId)->value('office_id');

        $runId = (string) Uuid7::generate();
        DB::table('pay_payroll_runs')->insert([
            'id' => $runId, 'office_id' => $officeId, 'period' => $period, 'status' => $status,
            'created_by' => $employeeId, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $payslipId = (string) Uuid7::generate();
        DB::table('pay_payslips')->insert([
            'id' => $payslipId, 'payroll_run_id' => $runId, 'employee_id' => $employeeId,
            'person_grade' => 5, 'salary_step' => 1,
            'imbalan_kerja_cents' => $takeHomeCents, 'tunjangan_jabatan_cents' => 0, 'tunjangan_penyesuaian_cents' => 0,
            'iuran_pensiun_pegawai_cents' => 0, 'iuran_tht_pegawai_cents' => 0, 'iuran_tht_bank_cents' => 0,
            'take_home_partial_cents' => $takeHomeCents, 'pending_components' => json_encode([]),
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $payslipId;
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
