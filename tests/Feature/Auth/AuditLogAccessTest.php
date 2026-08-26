<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

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
 * Log audit — lingkup BANK_WIDE, hanya-baca, independen dari peran
 * operasional lain (§6.3). Hanya Auditor yang berwenang membukanya.
 */
final class AuditLogAccessTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_auditor_dapat_melihat_log_audit(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2019.09.0177')->value('id');
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 2.0);

        app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );

        $response = $this->actingAs($this->userWithNrp('2020.01.0231'))->get('/log-audit');

        $response->assertOk();
        $response->assertSeeText('Dewi Lestari');
        $response->assertSeeText('submitted');
    }

    public function test_peran_lain_ditolak_dari_log_audit(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/log-audit');

        $response->assertForbidden();
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
