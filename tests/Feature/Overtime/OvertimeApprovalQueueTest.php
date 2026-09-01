<?php

declare(strict_types=1);

namespace Tests\Feature\Overtime;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Overtime\Application\SubmitOvertimeRequest;
use App\Modules\Overtime\Domain\OvertimeType;
use App\Notifications\RequestDecided;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SeedsOvertimeAttendance;
use Tests\TestCase;

/**
 * Regresi: ApprovalQueueController::decide() sebelumnya HANYA mengubah
 * ovt_requests.status pada penolakan — pending_hours yang dipesan saat
 * pengajuan (SubmitOvertimeRequest) TIDAK PERNAH dilepas, sehingga
 * plafon 18 jam/minggu pegawai terkunci PERMANEN oleh pengajuan yang
 * sudah ditolak (bug ditemukan lewat audit kode).
 */
final class OvertimeApprovalQueueTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_penolakan_tahap_1_melepas_jam_yang_dipesan_pada_kuota_mingguan(): void
    {
        // Siti — Officer, KC Mataram, berhak Lembur Biasa (DEC-90).
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $actor = new AuditActor(actorId: $employeeId, actorRole: 'pegawai');
        $workDate = new DateTimeImmutable('2026-09-02'); // Rabu, minggu 2026-08-31 s.d. 2026-09-06
        $this->seedOvertimeAttendance($employeeId, $workDate, 4.0);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $actor,
        );

        $requestId = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->value('id');

        $quotaBefore = (float) DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->value('pending_hours');
        $this->assertEquals(4.0, $quotaBefore, 'Pengajuan harus memesan 4 jam saat diajukan.');

        // Ahmad Fauzi — atasan_langsung KC Mataram (tahap 1).
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/lembur/{$requestId}/tolak");

        $response->assertRedirect(route('admin.approval-queue'));
        $this->assertSame('rejected', DB::table('ovt_requests')->where('id', $requestId)->value('status'));

        $quotaAfter = (float) DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->value('pending_hours');
        $this->assertEquals(0.0, $quotaAfter, 'Jam yang dipesan harus dilepas saat pengajuan ditolak.');

        // Pegawai mengajukan lagi (4 jam, hari lain minggu yang sama) —
        // kuota harus mencerminkan HANYA pengajuan baru ini (4 jam), bukan
        // 8 jam seolah sisa pengajuan yang ditolak masih ikut terhitung
        // (plafon mingguan cuma 18 jam/OVT_WEEKLY_CAP_HOURS, jadi "sisa
        // hantu" akan mempersempit ruang pengajuan berikutnya).
        $workDateTwo = new DateTimeImmutable('2026-09-03');
        $this->seedOvertimeAttendance($employeeId, $workDateTwo, 4.0);

        app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDateTwo,
            actor: $actor,
        );

        $quotaFinal = (float) DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->value('pending_hours');
        $this->assertEquals(4.0, $quotaFinal);
    }

    /**
     * Celah ditemukan lewat evaluasi PM/client (2026-08-27): pola SAMA
     * PERSIS LeaveApprovalQueueScopeTest — alasan penolakan WAJIB
     * tersimpan dan terkirim ke pemohon lewat RequestDecided.
     */
    public function test_penolakan_menyimpan_alasan_dan_mengirim_notifikasi_ke_pemohon(): void
    {
        Notification::fake();

        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id'); // Siti, KC Mataram
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 4.0);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );
        $requestId = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->value('id');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/lembur/{$requestId}/tolak", ['catatan' => 'Bukti absensi tidak sesuai jadwal shift.']);

        $response->assertRedirect(route('admin.approval-queue'));

        $row = DB::table('ovt_requests')->where('id', $requestId)->first();
        $this->assertNotNull($row);
        $this->assertSame('rejected', $row->status);
        $this->assertSame('Bukti absensi tidak sesuai jadwal shift.', $row->decision_note);

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === false && $n->reason === 'Bukti absensi tidak sesuai jadwal shift.',
        );
    }

    public function test_setuju_tahap_atasan_belum_final_tidak_mengirim_notifikasi(): void
    {
        Notification::fake();

        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 4.0);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: new AuditActor(actorId: $employeeId, actorRole: 'pegawai'),
        );
        $requestId = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->value('id');

        $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/lembur/{$requestId}/setujui");

        Notification::assertNothingSent();
    }

    public function test_setuju_tahap_pimpinan_final_mengirim_notifikasi_ke_pemohon(): void
    {
        Notification::fake();

        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $requestId = (string) Uuid7::generate();

        DB::table('ovt_requests')->insert([
            'id' => $requestId,
            'spkl_number' => 'SPKL/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'overtime_type' => 'regular',
            'work_date' => '2027-02-01',
            'planned_hours' => null,
            'payable_hours' => 4,
            'status' => 'pending_pimpinan',
            'atasan_approver_id' => (string) Uuid7::generate(),
            'atasan_decided_at' => now(),
            'approval_deadline' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, pimpinan_kantor KC Mataram
            ->post("/persetujuan/lembur/{$requestId}/setujui");

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === true,
        );
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
