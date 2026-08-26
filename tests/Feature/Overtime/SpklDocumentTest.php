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
 * SPKL (Surat Perintah Kerja Lembur) hanya sah dicetak SETELAH
 * disetujui, dan hanya oleh pihak yang relevan terhadap satu
 * pengajuan (pemohon, penyetuju, hr_admin kantor sendiri, atau
 * hr_approver/auditor bank-wide) — bukan siapa saja yang sedang masuk.
 */
final class SpklDocumentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_spkl_belum_tersedia_sebelum_disetujui(): void
    {
        $requestId = $this->submitApproved('2018.03.0142', approve: false);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->get("/lembur/spkl/{$requestId}");

        $response->assertNotFound();
    }

    public function test_pemohon_dapat_mengunduh_spkl_miliknya_sendiri(): void
    {
        $requestId = $this->submitApproved('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->get("/lembur/spkl/{$requestId}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pegawai_lain_tidak_bisa_mengunduh_spkl_orang_lain(): void
    {
        $requestId = $this->submitApproved('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->get("/lembur/spkl/{$requestId}");

        $response->assertForbidden();
    }

    public function test_hr_approver_dapat_mengunduh_spkl_bank_wide(): void
    {
        $requestId = $this->submitApproved('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061')) // hr_approver
            ->get("/lembur/spkl/{$requestId}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    private function submitApproved(string $nrp, bool $approve = true): string
    {
        $employeeId = $this->employeeId($nrp);
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 2.0);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );

        $requestId = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->value('id');

        if ($approve) {
            DB::table('ovt_requests')->where('id', $requestId)->update([
                'status' => 'approved',
                'approver_id' => $this->employeeId('2014.02.0061'),
                'decided_at' => now(),
            ]);
        }

        return $requestId;
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
