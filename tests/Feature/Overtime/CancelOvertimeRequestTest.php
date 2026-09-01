<?php

declare(strict_types=1);

namespace Tests\Feature\Overtime;

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
 * Celah ditemukan lewat evaluasi PM/client (2026-08-27) — pola SAMA
 * PERSIS Tests\Feature\Leave\CancelLeaveRequestTest. Batal HANYA boleh
 * selama status masih 'pending' (SEBELUM tahap 1 diputus).
 */
final class CancelOvertimeRequestTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_batal_saat_pending_melepas_jam_yang_dipesan(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti, KC Mataram
        $workDate = new DateTimeImmutable('2026-09-02');
        $requestId = $this->submitOvertime($employeeId, $workDate, 4.0);

        $quotaBefore = $this->pendingHours($employeeId);
        $this->assertEquals(4.0, $quotaBefore);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/lembur/{$requestId}/batal");

        $response->assertRedirect();

        $row = DB::table('ovt_requests')->where('id', $requestId)->first();
        $this->assertNotNull($row);
        $this->assertSame('cancelled', $row->status);
        $this->assertEquals(0.0, $this->pendingHours($employeeId), 'Jam yang dipesan harus dilepas saat dibatalkan.');
    }

    public function test_batal_gagal_setelah_tahap_1_diputus(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $workDate = new DateTimeImmutable('2026-09-02');
        $requestId = $this->submitOvertime($employeeId, $workDate, 4.0);

        DB::table('ovt_requests')->where('id', $requestId)->update([
            'status' => 'pending_pimpinan',
            'atasan_approver_id' => (string) Uuid7::generate(),
            'atasan_decided_at' => now(),
        ]);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/lembur/{$requestId}/batal");

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('pending_pimpinan', DB::table('ovt_requests')->where('id', $requestId)->value('status'));
    }

    public function test_tidak_bisa_membatalkan_pengajuan_milik_orang_lain(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $workDate = new DateTimeImmutable('2026-09-02');
        $requestId = $this->submitOvertime($employeeId, $workDate, 4.0);

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, bukan pemilik pengajuan
            ->post("/lembur/{$requestId}/batal");

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('pending', DB::table('ovt_requests')->where('id', $requestId)->value('status'));
    }

    private function submitOvertime(string $employeeId, DateTimeImmutable $workDate, float $hours): string
    {
        $this->seedOvertimeAttendance($employeeId, $workDate, $hours);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );

        return DB::table('ovt_requests')->where('spkl_number', $spklNumber)->value('id');
    }

    private function pendingHours(string $employeeId): float
    {
        return (float) DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->value('pending_hours');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        return User::query()->where('employee_id', $this->employeeId($nrp))->firstOrFail();
    }
}
