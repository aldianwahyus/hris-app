<?php

declare(strict_types=1);

namespace Tests\Feature\Overtime;

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
 * Rekap biaya lembur: hr_admin melihat kantornya sendiri (OFFICE),
 * hr_approver melihat seluruh bank (BANK_WIDE) — hanya SPKL yang
 * sudah disetujui yang masuk hitungan (bukan pending/ditolak).
 */
final class OvertimeRecapTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_hr_admin_hanya_melihat_rekap_kantornya_sendiri(): void
    {
        // Rina Marlina (hr_admin) — KCP Gerung.
        $this->approveOvertime('2021.05.0302', '2026-09-02', 2.0);
        // Siti — kantor berbeda.
        $this->approveOvertime('2018.03.0142', '2026-09-02', 3.0);

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->get('/pegawai/lembur-biaya?bulan=2026-09');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertDontSeeText('Siti');
    }

    public function test_hr_approver_melihat_rekap_seluruh_bank(): void
    {
        $this->approveOvertime('2021.05.0302', '2026-09-02', 2.0);
        $this->approveOvertime('2018.03.0142', '2026-09-02', 3.0);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061')) // hr_approver
            ->get('/pegawai/lembur-biaya?bulan=2026-09');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertSeeText('Siti');
    }

    public function test_pengajuan_pending_tidak_masuk_rekap(): void
    {
        $this->approveOvertime('2018.03.0142', '2026-09-02', 3.0, approve: false);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->get('/pegawai/lembur-biaya?bulan=2026-09');

        $response->assertOk();
        $response->assertDontSeeText('Siti');
    }

    public function test_pengajuan_yang_sudah_dicairkan_tetap_muncul_dengan_status_lunas(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $this->approveOvertime('2018.03.0142', '2026-09-02', 3.0);

        DB::table('ovt_requests')->where('employee_id', $employeeId)->update([
            'status' => 'disbursed',
            'disbursed_at' => now(),
            'disbursement_reference' => 'SPKL-BAYAR/2026/09/0001',
        ]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->get('/pegawai/lembur-biaya?bulan=2026-09');

        $response->assertOk();
        $response->assertSeeText('Siti');
        $response->assertSeeText('Sudah Dicairkan');
        $response->assertSeeText('SPKL-BAYAR/2026/09/0001');
    }

    private function approveOvertime(string $nrp, string $workDateStr, float $hours, bool $approve = true): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');
        $workDate = new DateTimeImmutable($workDateStr);
        $this->seedOvertimeAttendance($employeeId, $workDate, $hours);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );

        if ($approve) {
            DB::table('ovt_requests')->where('spkl_number', $spklNumber)->update([
                'status' => 'approved',
                'approver_id' => DB::table('emp_employees')->where('nrp', '2014.02.0061')->value('id'),
                'decided_at' => now(),
            ]);
        }
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
