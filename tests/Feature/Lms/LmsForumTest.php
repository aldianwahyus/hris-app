<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Social & Collaborative Learning (BRD §5.9) — posting/membalas
 * TERBUKA semua pegawai (tanpa permission), moderasi (hapus) HANYA HC
 * (permission:lms-catalog.manage).
 */
final class LmsForumTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_membuat_thread_dan_membalas(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');

        $store = $this->actingAs($siti)->post('/pelatihan/forum', [
            'title' => 'Diskusi Uji',
            'body' => 'Isi diskusi uji.',
        ]);

        $threadId = DB::table('lms_forum_threads')->where('title', 'Diskusi Uji')->value('id');
        $store->assertRedirect(route('lms.forum.show', $threadId));

        $ahmad = $this->userWithNrp('2015.07.0088');
        $reply = $this->actingAs($ahmad)->post("/pelatihan/forum/{$threadId}/balas", [
            'body' => 'Balasan uji.',
        ]);

        $reply->assertRedirect(route('lms.forum.show', $threadId));
        $this->assertSame(1, DB::table('lms_forum_replies')->where('thread_id', $threadId)->count());
    }

    public function test_hc_dapat_menghapus_thread(): void
    {
        $threadId = $this->seedThread('2018.03.0142');

        $response = $this->actingAs($this->hrAdmin())->delete("/admin/pelatihan/forum/{$threadId}");

        $response->assertRedirect(route('lms.forum.index'));
        $this->assertSame(0, DB::table('lms_forum_threads')->where('id', $threadId)->count());
    }

    public function test_pegawai_biasa_tidak_dapat_menghapus_thread(): void
    {
        $threadId = $this->seedThread('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->delete("/admin/pelatihan/forum/{$threadId}");

        $response->assertForbidden();
        $this->assertSame(1, DB::table('lms_forum_threads')->where('id', $threadId)->count());
    }

    private function seedThread(string $nrp): string
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');
        $id = (string) Uuid7::generate();

        DB::table('lms_forum_threads')->insert([
            'id' => $id, 'employee_id' => $employeeId, 'title' => 'Thread Uji', 'body' => 'Isi.',
            'is_pinned' => false, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
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
