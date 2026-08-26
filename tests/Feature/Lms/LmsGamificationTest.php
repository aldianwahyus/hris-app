<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Lms\Application\RecordCourseCompletion;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gamifikasi (BRD §5.8) — poin OTOMATIS dari peristiwa nyata (kelulusan
 * kursus/asesmen), badge diberikan manual HC, challenge ditandai
 * selesai oleh HC.
 */
final class LmsGamificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lulus_kursus_otomatis_menambah_poin(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->seedApprovedEnrollment($siti->employee_id);

        app(RecordCourseCompletion::class)->handle(
            enrollmentId: $enrollmentId,
            completionStatus: 'lulus',
            score: '90',
            actor: new AuditActor(actorId: $siti->employee_id, actorRole: 'pegawai'),
            recordedBy: $siti->employee_id,
        );

        $totalPoints = DB::table('lms_gamification_points')->where('employee_id', $siti->employee_id)->sum('points');
        $this->assertSame(10, $totalPoints);
    }

    public function test_hc_dapat_memberi_lencana(): void
    {
        $badgeId = $this->seedBadge();
        $siti = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/lencana/beri', [
            'employee_id' => $siti->employee_id,
            'badge_id' => $badgeId,
        ]);

        $response->assertRedirect(route('lms.admin.badges.index'));
        $this->assertSame(1, DB::table('lms_employee_badges')->where('employee_id', $siti->employee_id)->where('badge_id', $badgeId)->count());
    }

    public function test_lencana_tidak_bisa_diberikan_dua_kali(): void
    {
        $badgeId = $this->seedBadge();
        $siti = $this->userWithNrp('2018.03.0142');

        $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/lencana/beri', [
            'employee_id' => $siti->employee_id, 'badge_id' => $badgeId,
        ]);

        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/lencana/beri', [
            'employee_id' => $siti->employee_id, 'badge_id' => $badgeId,
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('lms_employee_badges')->where('employee_id', $siti->employee_id)->where('badge_id', $badgeId)->count());
    }

    public function test_menandai_challenge_selesai_menambah_poin(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $challengeId = $this->seedChallenge(20);

        $this->actingAs($siti)->post("/pelatihan/challenge/{$challengeId}/ikuti");

        $response = $this->actingAs($this->hrAdmin())
            ->post("/admin/pelatihan/challenge/{$challengeId}/peserta/{$siti->employee_id}/selesai");

        $response->assertRedirect(route('lms.admin.challenges.participants', $challengeId));
        $this->assertSame('completed', DB::table('lms_challenge_participants')->where('challenge_id', $challengeId)->where('employee_id', $siti->employee_id)->value('status'));
        $this->assertSame(20, (int) DB::table('lms_gamification_points')->where('employee_id', $siti->employee_id)->sum('points'));
    }

    public function test_papan_peringkat_urut_dari_poin_tertinggi(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $ahmad = $this->userWithNrp('2015.07.0088');

        DB::table('lms_gamification_points')->insert([
            ['id' => (string) Uuid7::generate(), 'employee_id' => $siti->employee_id, 'points' => 5, 'reason' => 'uji', 'created_at' => now()],
            ['id' => (string) Uuid7::generate(), 'employee_id' => $ahmad->employee_id, 'points' => 50, 'reason' => 'uji', 'created_at' => now()],
        ]);

        // Bertindak sebagai pegawai NETRAL (bukan Siti/Ahmad) — nama
        // pengguna yang sedang login tampil di navigasi (footer "kaki"),
        // jadi kalau salah satu dari keduanya yang login, namanya
        // muncul lebih awal di HTML terlepas dari urutan peringkat
        // sungguhan, mencemari perbandingan strpos di bawah.
        $netral = $this->userWithNrp('2020.01.0231');
        $response = $this->actingAs($netral)->get('/pelatihan/papan-peringkat');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, (string) $siti->employee?->full_name), strpos($content, (string) $ahmad->employee?->full_name));
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/pelatihan/lencana');

        $response->assertForbidden();
    }

    private function seedApprovedEnrollment(string $employeeId): string
    {
        $courseId = (string) Uuid7::generate();
        DB::table('lms_courses')->insert([
            'id' => $courseId, 'code' => 'CRS-'.uniqid(), 'title' => 'Kursus Uji',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $batchId = (string) Uuid7::generate();
        DB::table('lms_course_batches')->insert([
            'id' => $batchId, 'course_id' => $courseId, 'batch_code' => 'BATCH-'.uniqid(),
            'start_date' => now()->subDays(10)->format('Y-m-d'), 'end_date' => now()->subDays(8)->format('Y-m-d'),
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $enrollmentId = (string) Uuid7::generate();
        DB::table('lms_enrollments')->insert([
            'id' => $enrollmentId, 'enrollment_number' => 'PLT/TEST/'.uniqid(), 'batch_id' => $batchId,
            'employee_id' => $employeeId, 'status' => 'approved', 'requested_at' => now(),
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $enrollmentId;
    }

    private function seedBadge(): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_badges')->insert([
            'id' => $id, 'code' => 'BADGE-'.uniqid(), 'name' => 'Lencana Uji',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function seedChallenge(int $pointsReward): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_challenges')->insert([
            'id' => $id, 'title' => 'Challenge Uji', 'points_reward' => $pointsReward,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function hrAdmin(): User
    {
        return $this->userWithNrp('2021.05.0302');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->with('employee')->where('employee_id', $employeeId)->firstOrFail();
    }
}
