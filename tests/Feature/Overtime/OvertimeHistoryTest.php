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
 * Celah ditemukan lewat evaluasi PM/client (2026-08-27) — pola SAMA
 * PERSIS Tests\Feature\Leave\LeaveHistoryTest.
 */
final class OvertimeHistoryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_riwayat_hanya_menampilkan_pengajuan_milik_sendiri(): void
    {
        $siti = $this->employeeId('2018.03.0142'); // Officer, KC Mataram — berhak Lembur Biasa
        $budi = $this->employeeId('2020.01.0231'); // Officer, KCP Praya — berhak Lembur Biasa

        $sitiWorkDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($siti, $sitiWorkDate, 4.0);
        $sitiSpkl = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $siti,
            overtimeType: OvertimeType::Regular,
            workDate: $sitiWorkDate,
            actor: new AuditActor(actorId: $siti, actorRole: 'pegawai'),
        );

        $budiWorkDate = new DateTimeImmutable('2026-09-09');
        $this->seedOvertimeAttendance($budi, $budiWorkDate, 3.0);
        $budiSpkl = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $budi,
            overtimeType: OvertimeType::Regular,
            workDate: $budiWorkDate,
            actor: new AuditActor(actorId: $budi, actorRole: 'pegawai'),
        );

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/lembur/riwayat');

        $response->assertOk();
        $response->assertSee($sitiSpkl);
        $response->assertDontSee($budiSpkl);
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
