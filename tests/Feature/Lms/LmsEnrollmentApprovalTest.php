<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Notifications\RequestDecided;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pelatihan — 1 TAHAP, Atasan Langsung SAJA (office-tree), pola PERSIS
 * ShiftSwapApprovalController/OutsideAttendanceApprovalController.
 * Beda dari Tukar Shift: di sini keputusan DICATAT ke audit trail
 * (AuditAction::Approved/Rejected + contextRef), lihat DecideEnrollment.
 */
final class LmsEnrollmentApprovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_atasan_langsung_dapat_menyetujui_pendaftaran_bawahannya(): void
    {
        [$enrollmentId, $enrollmentNumber] = $this->insertEnrollment($this->employeeId('2018.03.0142'));

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, atasan_langsung KC Mataram
            ->post("/persetujuan/pelatihan/{$enrollmentId}/setujui");

        $response->assertRedirect(route('admin.lms-enrollment-queue'));
        $this->assertSame('approved', DB::table('lms_enrollments')->where('id', $enrollmentId)->value('status'));

        $audit = DB::table('aud_change_logs')->where('auditable_id', $enrollmentId)->where('action', 'approved')->first();
        $this->assertNotNull($audit);
        $this->assertSame($enrollmentNumber, $audit->context_ref);
    }

    public function test_atasan_kantor_lain_ditolak_melihat_atau_memutus(): void
    {
        [$enrollmentId] = $this->insertEnrollment($this->employeeId('2018.03.0142')); // KC Mataram
        $dewi = $this->userWithNrp('2019.09.0177'); // KC Selong — bukan pohon kantor Mataram
        $this->grantRole($dewi, 'atasan_langsung');

        $response = $this->actingAs($dewi)->post("/persetujuan/pelatihan/{$enrollmentId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('lms_enrollments')->where('id', $enrollmentId)->value('status'));
    }

    public function test_pemohon_tidak_dapat_menyetujui_pendaftarannya_sendiri(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');
        [$enrollmentId] = $this->insertEnrollment($sitiId);
        $this->grantRole($this->userWithNrp('2018.03.0142'), 'atasan_langsung');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/persetujuan/pelatihan/{$enrollmentId}/setujui");

        $response->assertForbidden();
    }

    public function test_auditor_hanya_baca_ditolak_memutus(): void
    {
        [$enrollmentId] = $this->insertEnrollment($this->employeeId('2018.03.0142'));

        $response = $this->actingAs($this->userWithNrp('2020.01.0231')) // Dewi Lestari, auditor
            ->post("/persetujuan/pelatihan/{$enrollmentId}/setujui");

        $response->assertForbidden();
    }

    public function test_penolakan_tercatat_di_audit_dengan_context_ref(): void
    {
        [$enrollmentId, $enrollmentNumber] = $this->insertEnrollment($this->employeeId('2018.03.0142'));

        $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/pelatihan/{$enrollmentId}/tolak")
            ->assertRedirect(route('admin.lms-enrollment-queue'));

        $this->assertSame('rejected', DB::table('lms_enrollments')->where('id', $enrollmentId)->value('status'));

        $audit = DB::table('aud_change_logs')->where('auditable_id', $enrollmentId)->where('action', 'rejected')->first();
        $this->assertNotNull($audit);
        $this->assertSame($enrollmentNumber, $audit->context_ref);
    }

    public function test_peran_lain_ditolak_dari_antrean(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/persetujuan/pelatihan');

        $response->assertForbidden();
    }

    /**
     * Celah ditemukan lewat evaluasi PM/client (2026-08-27) — pola SAMA
     * PERSIS LeaveApprovalQueueScopeTest. Pendaftaran pelatihan SATU
     * tahap: setiap keputusan selalu final, notifikasi SELALU terkirim.
     */
    public function test_penolakan_menyimpan_alasan_dan_mengirim_notifikasi_ke_pemohon(): void
    {
        Notification::fake();

        [$enrollmentId] = $this->insertEnrollment($this->employeeId('2018.03.0142'));

        $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/pelatihan/{$enrollmentId}/tolak", ['catatan' => 'Kuota batch sudah penuh oleh pendaftar lain.']);

        $this->assertSame('Kuota batch sudah penuh oleh pendaftar lain.', DB::table('lms_enrollments')->where('id', $enrollmentId)->value('decision_note'));

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === false && $n->reason === 'Kuota batch sudah penuh oleh pendaftar lain.',
        );
    }

    public function test_setuju_mengirim_notifikasi_ke_pemohon(): void
    {
        Notification::fake();

        [$enrollmentId] = $this->insertEnrollment($this->employeeId('2018.03.0142'));

        $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/pelatihan/{$enrollmentId}/setujui");

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === true,
        );
    }

    public function test_batal_saat_pending_berhasil(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        [$enrollmentId] = $this->insertEnrollment($employeeId);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/pelatihan/{$enrollmentId}/batal");

        $response->assertRedirect();
        $this->assertSame('cancelled', DB::table('lms_enrollments')->where('id', $enrollmentId)->value('status'));
    }

    public function test_batal_gagal_setelah_diputus(): void
    {
        [$enrollmentId] = $this->insertEnrollment($this->employeeId('2018.03.0142'));
        DB::table('lms_enrollments')->where('id', $enrollmentId)->update(['status' => 'approved']);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/pelatihan/{$enrollmentId}/batal");

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('approved', DB::table('lms_enrollments')->where('id', $enrollmentId)->value('status'));
    }

    /** @return array{0: string, 1: string} [enrollmentId, enrollmentNumber] */
    private function insertEnrollment(string $employeeId): array
    {
        $courseId = (string) Uuid7::generate();
        DB::table('lms_courses')->insert([
            'id' => $courseId,
            'code' => 'UJI-'.uniqid(),
            'title' => 'Kursus Uji',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $batchId = (string) Uuid7::generate();
        DB::table('lms_course_batches')->insert([
            'id' => $batchId,
            'course_id' => $courseId,
            'batch_code' => 'BATCH-'.uniqid(),
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(9)->format('Y-m-d'),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $id = (string) Uuid7::generate();
        $number = 'PLT/TEST/'.uniqid();

        DB::table('lms_enrollments')->insert([
            'id' => $id,
            'enrollment_number' => $number,
            'batch_id' => $batchId,
            'employee_id' => $employeeId,
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return [$id, $number];
    }

    private function grantRole(User $user, string $roleName): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        $alreadyHas = DB::table('model_has_roles')->where('model_id', $user->id)->where('role_id', $roleId)->exists();

        if (! $alreadyHas) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        }
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
