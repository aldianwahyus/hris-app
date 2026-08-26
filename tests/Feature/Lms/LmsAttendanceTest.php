<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Absensi per sesi (BRD §5.3) — HC mencatat kehadiran per pendaftar
 * approved, per hari pertemuan (bukan satu flag untuk seluruh batch).
 */
final class LmsAttendanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hc_dapat_menambah_sesi_untuk_batch(): void
    {
        $batchId = $this->seedBatch();

        $response = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/jadwal/{$batchId}/sesi", [
            'sequence' => 1,
            'session_date' => now()->addDays(7)->format('Y-m-d'),
            'topic' => 'Pembukaan',
        ]);

        $response->assertRedirect(route('lms.admin.batches.sessions', $batchId));
        $this->assertSame(1, DB::table('lms_course_sessions')->where('batch_id', $batchId)->count());
    }

    public function test_hc_dapat_mencatat_absensi_pendaftar_approved(): void
    {
        $batchId = $this->seedBatch();
        $sessionId = $this->seedSession($batchId, 1);
        $enrollmentId = $this->seedEnrollment($batchId, '2018.03.0142', 'approved');

        $response = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/sesi/{$sessionId}/absensi", [
            'kehadiran' => [$enrollmentId => 'hadir'],
        ]);

        $response->assertRedirect(route('lms.admin.sessions.attendance', $sessionId));

        $attendance = DB::table('lms_attendances')->where('session_id', $sessionId)->where('enrollment_id', $enrollmentId)->first();
        $this->assertNotNull($attendance);
        $this->assertSame('hadir', $attendance->status);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'lms_session_attendance')->where('auditable_id', $sessionId)
            ->where('action', 'updated')->first();
        $this->assertNotNull($audit);
    }

    public function test_absensi_dapat_dikoreksi_ulang(): void
    {
        $batchId = $this->seedBatch();
        $sessionId = $this->seedSession($batchId, 1);
        $enrollmentId = $this->seedEnrollment($batchId, '2018.03.0142', 'approved');

        $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/sesi/{$sessionId}/absensi", [
            'kehadiran' => [$enrollmentId => 'hadir'],
        ]);
        $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/sesi/{$sessionId}/absensi", [
            'kehadiran' => [$enrollmentId => 'sakit'],
        ]);

        $this->assertSame(1, DB::table('lms_attendances')->where('session_id', $sessionId)->count());
        $this->assertSame('sakit', DB::table('lms_attendances')->where('session_id', $sessionId)->value('status'));
    }

    public function test_roster_absensi_hanya_menampilkan_pendaftar_approved(): void
    {
        $batchId = $this->seedBatch();
        $sessionId = $this->seedSession($batchId, 1);
        $this->seedEnrollment($batchId, '2018.03.0142', 'pending');

        $response = $this->actingAs($this->hrAdmin())->get("/admin/pelatihan/sesi/{$sessionId}/absensi");

        $response->assertOk();
        $response->assertDontSeeText('Siti Rahmawati');
    }

    public function test_rekap_kehadiran_tampil_di_pelatihan_saya(): void
    {
        $batchId = $this->seedBatch();
        $sessionId1 = $this->seedSession($batchId, 1);
        $sessionId2 = $this->seedSession($batchId, 2);
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->seedEnrollment($batchId, '2018.03.0142', 'approved');

        $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/sesi/{$sessionId1}/absensi", [
            'kehadiran' => [$enrollmentId => 'hadir'],
        ]);
        $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/sesi/{$sessionId2}/absensi", [
            'kehadiran' => [$enrollmentId => 'alpa'],
        ]);

        $response = $this->actingAs($siti)->get('/pelatihan/saya');

        $response->assertOk();
        $response->assertSeeText('1/2 hari');
    }

    public function test_peran_lain_ditolak_dari_absensi(): void
    {
        $batchId = $this->seedBatch();
        $sessionId = $this->seedSession($batchId, 1);

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get("/admin/pelatihan/sesi/{$sessionId}/absensi");

        $response->assertForbidden();
    }

    private function seedBatch(): string
    {
        $courseId = (string) Uuid7::generate();
        DB::table('lms_courses')->insert([
            'id' => $courseId, 'code' => 'UJI-'.uniqid(), 'title' => 'Kursus Uji',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $batchId = (string) Uuid7::generate();
        DB::table('lms_course_batches')->insert([
            'id' => $batchId, 'course_id' => $courseId, 'batch_code' => 'BATCH-'.uniqid(),
            'start_date' => now()->addDays(7)->format('Y-m-d'), 'end_date' => now()->addDays(9)->format('Y-m-d'),
            'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $batchId;
    }

    private function seedSession(string $batchId, int $sequence): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_course_sessions')->insert([
            'id' => $id, 'batch_id' => $batchId, 'sequence' => $sequence,
            'session_date' => now()->addDays(6 + $sequence)->format('Y-m-d'),
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function seedEnrollment(string $batchId, string $nrp, string $status): string
    {
        $employeeId = $this->employeeId($nrp);
        $id = (string) Uuid7::generate();

        DB::table('lms_enrollments')->insert([
            'id' => $id, 'enrollment_number' => 'PLT/TEST/'.uniqid(), 'batch_id' => $batchId,
            'employee_id' => $employeeId, 'status' => $status, 'requested_at' => now(),
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function hrAdmin(): User
    {
        return $this->userWithNrp('2021.05.0302');
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
