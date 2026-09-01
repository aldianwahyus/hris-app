<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Models\User;
use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\LeaveType;
use App\Modules\Overtime\Application\SubmitOvertimeRequest;
use App\Modules\Overtime\Domain\OvertimeType;
use App\Notifications\ApprovalSlaExpired;
use App\Notifications\ApprovalSlaReminder;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SeedsOvertimeAttendance;
use Tests\TestCase;

/**
 * Tahap 3 — Penjadwal Pengingat SLA. Mitigasi risiko RA-3 (ARCH-001 §10):
 * persetujuan lembur terpusat pada satu pejabat, sementara keterlambatan
 * menghanguskan hak bayar pegawai. Pengingat tidak boleh terkirim ganda
 * maupun terlewat — lihat juga regresi pada SlaWindowTest.
 *
 * Lembur SEKARANG 2 TAHAP (koreksi atas DEC-92 versi awal — lihat
 * ApprovalQueueController/ResolveApprovalAudience): pengajuan yang
 * masih status 'pending' (tahap 1, BELUM diputus siapa pun) diberi
 * tahu ke Atasan Langsung yang office-tree-nya mencakup kantor pemohon
 * — Ahmad Fauzi (atasan_langsung, KC Mataram + KCP Praya) dipakai
 * sebagai titik ukur H-7/H-6/H-3 di bawah karena NRP contoh yang
 * dipakai (Budi/Hendra) berkantor dalam pohon itu. Cuti SEKARANG
 * memakai pola 2 tahap yang SAMA (lihat LeaveApprovalQueueController/
 * ResolveApprovalAudience) — hr_approver DIHAPUS dari audiens Cuti
 * juga. Nur Aisyah (hr_approver, DAN pimpinan_kantor Kantor Pusat)
 * dipakai HANYA sebagai pembanding negatif (TIDAK seharusnya menerima
 * pengingat tahap 1 yang bukan miliknya).
 */
final class ProcessSlaRemindersTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_pengingat_h7_terkirim_dan_tercatat_agar_tidak_terkirim_ganda(): void
    {
        Notification::fake();

        // Siti — Officer, KC Mataram (dalam pohon kantor Ahmad Fauzi).
        $instanceId = $this->submitOvertimeAndBackdate(nrp: '2018.03.0142', remainingDays: 6);

        $this->artisan('workflow:process-sla')->assertExitCode(0);

        $step = DB::table('wf_instance_steps')->where('instance_id', $instanceId)->first();
        $this->assertSame([7], json_decode((string) $step->reminders_sent, true));
        Notification::assertSentToTimes($this->ahmadFauzi(), ApprovalSlaReminder::class, 1);

        // Menjalankan lagi pada hari yang sama TIDAK boleh mengirim ulang.
        $this->artisan('workflow:process-sla')->assertExitCode(0);

        $step = DB::table('wf_instance_steps')->where('instance_id', $instanceId)->first();
        $this->assertSame([7], json_decode((string) $step->reminders_sent, true), 'Ambang H-7 tidak boleh terkirim dua kali.');
        Notification::assertSentToTimes($this->ahmadFauzi(), ApprovalSlaReminder::class, 1);
    }

    /**
     * Regresi langsung dari SlaWindowTest::test_tidak_melewatkan_pengingat_berikutnya_setelah_yang_pertama_terkirim():
     * pada H-6 dengan H-7 sudah terkirim, belum saatnya H-3 — sistem
     * harus diam, BUKAN menganggap seluruh pengingat selesai.
     */
    public function test_pada_h6_setelah_h7_terkirim_belum_saatnya_h3(): void
    {
        Notification::fake();

        $instanceId = $this->submitOvertimeAndBackdate(nrp: '2020.01.0231', remainingDays: 7);
        $this->artisan('workflow:process-sla'); // kirim H-7

        Notification::assertSentToTimes($this->ahmadFauzi(), ApprovalSlaReminder::class, 1);

        // Majukan waktu ke H-6 (satu hari berlalu tanpa mengubah "sekarang").
        DB::table('wf_instance_steps')->where('instance_id', $instanceId)
            ->update(['sla_due_at' => now()->addDays(6)]);

        $this->artisan('workflow:process-sla');

        $step = DB::table('wf_instance_steps')->where('instance_id', $instanceId)->first();
        $this->assertSame([7], json_decode((string) $step->reminders_sent, true), 'H-3 belum saatnya pada H-6.');
        Notification::assertSentToTimes($this->ahmadFauzi(), ApprovalSlaReminder::class, 1);
    }

    public function test_pengingat_h3_terkirim_setelah_h7_tanpa_mengulang_h7(): void
    {
        Notification::fake();

        $instanceId = $this->submitOvertimeAndBackdate(nrp: '2017.11.0119', remainingDays: 3);

        DB::table('wf_instance_steps')->where('instance_id', $instanceId)
            ->update(['reminders_sent' => json_encode([7])]);

        $this->artisan('workflow:process-sla');

        $step = DB::table('wf_instance_steps')->where('instance_id', $instanceId)->first();
        $this->assertSame([7, 3], json_decode((string) $step->reminders_sent, true));
        Notification::assertSentToTimes($this->ahmadFauzi(), ApprovalSlaReminder::class, 1);
    }

    public function test_lembur_kedaluwarsa_ditandai_melepas_kuota_dan_memberi_tahu_pemutus(): void
    {
        Notification::fake();

        // Siti (Officer, KC Mataram) — berhak Lembur Biasa; Branch
        // Manager/Division Head dkk. tidak berhak (DEC-90), lihat
        // SubmitOvertimeRequestTest untuk itu. Dipilih dalam pohon kantor
        // Ahmad Fauzi (atasan_langsung) agar pengingat tahap 1 terkirim.
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $actor = new AuditActor(actorId: $employeeId, actorRole: 'pegawai');
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 4.0);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $actor,
        );

        $request = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->first();

        DB::table('wf_instance_steps')->where('instance_id', $request->wf_instance_id)->update([
            'started_at' => now()->subDays(31),
            'sla_due_at' => now()->subDay(),
        ]);

        $quotaBefore = DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->value('pending_hours');
        $this->assertEquals(4.0, (float) $quotaBefore);

        $this->artisan('workflow:process-sla')->assertExitCode(0);

        $this->assertSame('expired', DB::table('ovt_requests')->where('id', $request->id)->value('status'));
        $this->assertSame('expired', DB::table('wf_instances')->where('id', $request->wf_instance_id)->value('status'));

        $quotaAfter = DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->value('pending_hours');
        $this->assertEquals(0.0, (float) $quotaAfter, 'Jam yang dipesan harus dilepas ketika kedaluwarsa.');

        $audit = DB::table('aud_change_logs')->where('auditable_id', $request->id)->where('action', 'expired')->first();
        $this->assertNotNull($audit);
        $this->assertSame($spklNumber, $audit->context_ref);

        Notification::assertSentToTimes($this->ahmadFauzi(), ApprovalSlaExpired::class, 1);
    }

    public function test_cuti_kedaluwarsa_tidak_menyentuh_kuota_lembur(): void
    {
        Notification::fake();

        // Siti — Officer, KC Mataram (dalam pohon kantor Ahmad Fauzi,
        // BUKAN Ahmad sendiri, supaya tidak tercampur kasus dia
        // menerima notifikasi atas pengajuannya sendiri).
        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $actor = new AuditActor(actorId: $employeeId, actorRole: 'pegawai');

        $usedDaysBefore = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');

        // 2026-09-01 (Selasa) s.d. 2026-09-07 (Senin) = 5 HARI KERJA murni
        // (total_days sekarang dihitung hari kerja, bukan kalender mentah
        // — lihat SubmitLeaveRequestTest).
        $requestNumber = app(SubmitLeaveRequest::class)->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-07'),
            reason: null,
            actor: $actor,
        );

        $request = DB::table('leave_requests')->where('request_number', $requestNumber)->first();

        $usedDaysAfterSubmit = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');
        $this->assertEquals($usedDaysBefore + 5.0, $usedDaysAfterSubmit, 'Pengajuan harus mendebit 5 hari saat diajukan.');

        DB::table('wf_instance_steps')->where('instance_id', $request->wf_instance_id)->update([
            'started_at' => now()->subDays(6),
            'sla_due_at' => now()->subDay(),
        ]);

        $this->artisan('workflow:process-sla')->assertExitCode(0);

        $this->assertSame('expired', DB::table('leave_requests')->where('id', $request->id)->value('status'));

        // Bug ditemukan lewat audit kode, diperbaiki hari ini: pengajuan
        // yang kedaluwarsa WAJIB mengembalikan hari yang sudah terpotong
        // saat diajukan — sebelumnya jatah cuti pegawai hangus permanen.
        $usedDaysAfterExpiry = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');
        $this->assertEquals($usedDaysBefore, $usedDaysAfterExpiry, 'Hari cuti harus dikembalikan penuh saat pengajuan kedaluwarsa.');
        $this->assertNull(DB::table('leave_requests')->where('id', $request->id)->value('bucket_debits'), 'Snapshot debit harus dikosongkan setelah dilepas.');
        // Cuti SEKARANG 2 tahap sama seperti Lembur — tahap 1 (status
        // 'pending' sebelum kedaluwarsa) diberi tahu ke Atasan Langsung
        // (Ahmad Fauzi, KC Mataram), BUKAN lagi hr_approver.
        Notification::assertSentToTimes($this->ahmadFauzi(), ApprovalSlaExpired::class, 1);
        Notification::assertNotSentTo($this->nurAisyah(), ApprovalSlaExpired::class);
    }

    /**
     * Regresi (bug ditemukan lewat audit kode): approval controller
     * (LeaveApprovalQueueController dkk.) menulis keputusan LANGSUNG ke
     * leave_requests.status, TIDAK PERNAH memanggil
     * WorkflowInstance::decide()/save() — jadi wf_instances/
     * wf_instance_steps tetap 'pending' SELAMANYA meski pengajuan sudah
     * disetujui. Tanpa perbaikan documentStillPending()/
     * reconcileAlreadyDecided(), sapuan SLA yang berjalan setelah tenggat
     * (dihitung dari SUBMIT, bukan dari keputusan) akan salah menganggap
     * pengajuan yang SUDAH disetujui sebagai kedaluwarsa: menimpa
     * status='expired' (dicegah whereIn status di markDocumentExpired,
     * TIDAK berlaku di sini karena status sudah 'approved') DAN — yang
     * jauh lebih berbahaya — mengembalikan saldo cuti yang sudah benar
     * dipakai/disetujui.
     */
    public function test_cuti_yang_sudah_disetujui_tidak_ikut_kedaluwarsa_walau_lewat_tenggat_sla(): void
    {
        Notification::fake();

        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $actor = new AuditActor(actorId: $employeeId, actorRole: 'pegawai');

        $usedDaysBefore = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');

        $requestNumber = app(SubmitLeaveRequest::class)->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-02'),
            reason: null,
            actor: $actor,
        );

        $request = DB::table('leave_requests')->where('request_number', $requestNumber)->first();

        // Setujui KEDUA tahap secara langsung lewat tabel bisnis — PERSIS
        // seperti yang dilakukan LeaveApprovalQueueController::decide(),
        // TANPA pernah menyentuh wf_instances/wf_instance_steps (itulah
        // intinya: baris Workflow Engine sengaja dibiarkan basi di sini).
        DB::table('leave_requests')->where('id', $request->id)->update([
            'status' => 'approved',
            'approver_id' => $employeeId,
            'decided_at' => now(),
        ]);

        // Mundurkan tenggat SLA jauh ke belakang — seolah sapuan berjalan
        // lama setelah pengajuan SEHARUSNYA sudah diputus.
        DB::table('wf_instance_steps')->where('instance_id', $request->wf_instance_id)->update([
            'started_at' => now()->subDays(31),
            'sla_due_at' => now()->subDay(),
        ]);

        $this->artisan('workflow:process-sla')->assertExitCode(0);

        $this->assertSame('approved', DB::table('leave_requests')->where('id', $request->id)->value('status'), 'Pengajuan yang sudah disetujui TIDAK BOLEH berubah jadi expired.');

        $usedDaysAfter = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');
        $this->assertEquals($usedDaysBefore + 2.0, $usedDaysAfter, 'Saldo cuti yang sudah disetujui TIDAK BOLEH dikembalikan oleh sapuan SLA.');

        $this->assertNotNull(DB::table('leave_requests')->where('id', $request->id)->value('bucket_debits'), 'Snapshot debit TIDAK BOLEH dikosongkan untuk pengajuan yang sudah disetujui.');

        Notification::assertNothingSent();
    }

    public function test_pengingat_tahap_1_dikirim_ke_atasan_langsung_bukan_pimpinan_kantor(): void
    {
        Notification::fake();

        // Budi, KCP Praya — dalam pohon kantor Ahmad Fauzi (atasan_langsung).
        // Pengajuan MASIH status 'pending' (tahap 1, belum diputus siapa pun).
        $this->submitOvertimeAndBackdate(nrp: '2020.01.0231', remainingDays: 7);

        $this->artisan('workflow:process-sla');

        Notification::assertSentTo($this->ahmadFauzi(), ApprovalSlaReminder::class);
        // Nur Aisyah HANYA pimpinan_kantor (belum gilirannya di tahap 1)
        // — dan lagipula Kantor Pusat tidak terkait KCP Praya.
        Notification::assertNotSentTo($this->nurAisyah(), ApprovalSlaReminder::class);
    }

    public function test_pengingat_tahap_2_dikirim_ke_pimpinan_kantor_bukan_atasan_langsung(): void
    {
        Notification::fake();

        // Siti, KC Mataram — kantor yang SAMA dipimpin Ahmad Fauzi
        // (Branch Manager, jadi pimpinan_kantor KC Mataram) yang JUGA
        // atasan_langsung KC Mataram di data contoh. Cabut atasan_langsung
        // darinya SEMENTARA (di dalam pengujian ini saja) supaya
        // pengingat tahap 2 yang tetap sampai ke dia TERBUKTI lewat jalur
        // pimpinan_kantor, bukan sisa logika atasan_langsung lama.
        // Siti SUDAH punya pengajuan lembur bawaan data contoh
        // (SPKL/2026/08/0412, lihat 2026_01_01_000007_seed_sample_data)
        // — cari lewat wf_instance_id (unik per pengiriman) SUPAYA tidak
        // salah ambil baris lama itu.
        $instanceId = $this->submitOvertimeAndBackdate(nrp: '2018.03.0142', remainingDays: 7);
        $requestId = DB::table('wf_instances')->where('id', $instanceId)->value('document_id');

        DB::table('ovt_requests')->where('id', $requestId)->update([
            'status' => 'pending_pimpinan',
            'atasan_approver_id' => $this->employeeId('2019.09.0177'), // Dewi — pemutus tahap 1 hipotetis, di luar cakupan test ini
            'atasan_decided_at' => now(),
        ]);

        $this->revokeRole($this->ahmadFauzi(), 'atasan_langsung');

        $this->artisan('workflow:process-sla');

        Notification::assertSentTo($this->ahmadFauzi(), ApprovalSlaReminder::class);
        Notification::assertNotSentTo($this->nurAisyah(), ApprovalSlaReminder::class); // pimpinan_kantor Kantor Pusat, kantor berbeda
    }

    private function revokeRole(User $user, string $roleName): void
    {
        DB::table('model_has_roles')
            ->where('model_id', $user->getKey())
            ->where('model_type', User::class)
            ->where('role_id', DB::table('roles')->where('name', $roleName)->value('id'))
            ->delete();
    }

    private function submitOvertimeAndBackdate(string $nrp, int $remainingDays): string
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');
        $actor = new AuditActor(actorId: $employeeId, actorRole: 'pegawai');
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 2.0);

        $spklNumber = app(SubmitOvertimeRequest::class)->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $actor,
        );

        $request = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->first();

        DB::table('wf_instance_steps')->where('instance_id', $request->wf_instance_id)->update([
            'started_at' => now()->subDays(30 - $remainingDays),
            'sla_due_at' => now()->addDays($remainingDays),
        ]);

        return $request->wf_instance_id;
    }

    private function nurAisyah(): User
    {
        return $this->userForNrp('2014.02.0061');
    }

    private function ahmadFauzi(): User
    {
        return $this->userForNrp('2015.07.0088');
    }

    private function userForNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }
}
