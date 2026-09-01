<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\LeaveType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Celah ditemukan lewat evaluasi PM/client (2026-08-27): tidak ada cara
 * bagi pegawai membatalkan pengajuan sendiri sebelum diputus — harus
 * minta atasan MENOLAK secara manual. Batal HANYA boleh selama status
 * masih 'pending' (SEBELUM tahap 1 diputus, konfirmasi user).
 */
final class CancelLeaveRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_batal_saat_pending_mengembalikan_saldo_dan_menghapus_snapshot_debit(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti, KC Mataram
        $usedDaysBefore = $this->usedDays($employeeId);

        $requestId = $this->submitFiveDays($employeeId);
        $this->assertEquals($usedDaysBefore + 5.0, $this->usedDays($employeeId));

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/cuti/{$requestId}/batal");

        $response->assertRedirect();

        $row = DB::table('leave_requests')->where('id', $requestId)->first();
        $this->assertSame('cancelled', $row->status);
        $this->assertNull($row->bucket_debits, 'Snapshot debit harus dikosongkan setelah dilepas.');
        $this->assertEquals($usedDaysBefore, $this->usedDays($employeeId), 'Hari cuti harus dikembalikan penuh saat dibatalkan.');
    }

    public function test_batal_gagal_setelah_tahap_1_diputus(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $requestId = $this->submitFiveDays($employeeId);

        DB::table('leave_requests')->where('id', $requestId)->update([
            'status' => 'pending_pimpinan',
            'atasan_approver_id' => (string) Uuid7::generate(),
            'atasan_decided_at' => now(),
        ]);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/cuti/{$requestId}/batal");

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('pending_pimpinan', DB::table('leave_requests')->where('id', $requestId)->value('status'));
    }

    public function test_tidak_bisa_membatalkan_pengajuan_milik_orang_lain(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $requestId = $this->submitFiveDays($employeeId);

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, bukan pemilik pengajuan
            ->post("/cuti/{$requestId}/batal");

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('pending', DB::table('leave_requests')->where('id', $requestId)->value('status'));
    }

    private function submitFiveDays(string $employeeId): string
    {
        // 2026-09-01 (Selasa) s.d. 2026-09-07 (Senin) = 5 HARI KERJA murni.
        $requestNumber = app(SubmitLeaveRequest::class)->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-07'),
            reason: null,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );

        return DB::table('leave_requests')->where('request_number', $requestNumber)->value('id');
    }

    private function usedDays(string $employeeId): float
    {
        return (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');
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
