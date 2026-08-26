<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Live Learning & Mentoring (BRD §5.10) — penjadwalan + tautan
 * eksternal (tidak ada hosting video sendiri).
 */
final class LmsLiveSessionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hc_dapat_menjadwalkan_sesi(): void
    {
        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/sesi-live', [
            'title' => 'Webinar Uji',
            'session_type' => 'webinar',
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'meeting_url' => 'https://example.com/meet',
        ]);

        $response->assertRedirect(route('lms.admin.live-sessions.index'));
        $this->assertSame(1, DB::table('lms_live_sessions')->where('title', 'Webinar Uji')->count());
    }

    public function test_pegawai_dapat_mendaftar_dan_hc_dapat_menandai_hadir(): void
    {
        $sessionId = $this->seedSession();
        $siti = $this->userWithNrp('2018.03.0142');

        $register = $this->actingAs($siti)->post("/pelatihan/sesi-live/{$sessionId}/daftar");
        $register->assertRedirect(route('lms.live-sessions.index'));

        $response = $this->actingAs($this->hrAdmin())
            ->post("/admin/pelatihan/sesi-live/{$sessionId}/peserta/{$siti->employee_id}/hadir");

        $response->assertRedirect(route('lms.admin.live-sessions.participants', $sessionId));
        $this->assertSame('attended', DB::table('lms_live_session_participants')
            ->where('session_id', $sessionId)->where('employee_id', $siti->employee_id)->value('status'));
    }

    public function test_pegawai_tidak_bisa_mendaftar_dua_kali(): void
    {
        $sessionId = $this->seedSession();
        $siti = $this->userWithNrp('2018.03.0142');

        $this->actingAs($siti)->post("/pelatihan/sesi-live/{$sessionId}/daftar");
        $response = $this->actingAs($siti)->post("/pelatihan/sesi-live/{$sessionId}/daftar");

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('lms_live_session_participants')->where('session_id', $sessionId)->count());
    }

    public function test_peran_lain_ditolak_dari_admin_sesi_live(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/pelatihan/sesi-live');

        $response->assertForbidden();
    }

    private function seedSession(): string
    {
        $id = (string) Uuid7::generate();

        DB::table('lms_live_sessions')->insert([
            'id' => $id, 'title' => 'Coaching Uji', 'session_type' => 'coaching',
            'scheduled_at' => now()->addDays(2), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
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

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
