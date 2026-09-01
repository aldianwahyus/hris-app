<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\LeaveType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Celah ditemukan lewat evaluasi PM/client (2026-08-27): dashboard ESS
 * membatasi ke 3 baris terbaru — tidak ada halaman "Riwayat Cuti Saya"
 * yang lengkap. Riwayat WAJIB hanya menampilkan pengajuan milik sendiri.
 */
final class LeaveHistoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_riwayat_hanya_menampilkan_pengajuan_milik_sendiri(): void
    {
        $siti = $this->employeeId('2018.03.0142');
        $ahmad = $this->employeeId('2015.07.0088');

        $sitiRequestNumber = $this->submit($siti, '2026-09-01', '2026-09-07');
        $ahmadRequestNumber = $this->submit($ahmad, '2026-10-01', '2026-10-07');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/cuti/riwayat');

        $response->assertOk();
        $response->assertSee($sitiRequestNumber);
        $response->assertDontSee($ahmadRequestNumber);
    }

    private function submit(string $employeeId, string $start, string $end): string
    {
        return app(SubmitLeaveRequest::class)->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable($start),
            endDate: new DateTimeImmutable($end),
            reason: null,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );
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
