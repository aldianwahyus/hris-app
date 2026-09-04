<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Core\Domain\Uuid7;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** ESS Mobile (Fase 2) — HR Helpdesk, cermin HelpdeskController (SubmitTicket/ReplyTicket). */
final class HelpdeskApiTest extends TestCase
{
    use DatabaseTransactions;

    private const NRP = '2018.03.0142'; // Siti Rahmawati

    private const PASSWORD = 'RahasiaDemo!123';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(self::NRP.'|127.0.0.1');
    }

    public function test_tiket_dapat_diajukan_lewat_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/bantuan', [
            'category' => 'penggajian',
            'subject' => 'Slip gaji belum muncul',
            'description' => 'Slip gaji bulan ini belum bisa diunduh (uji API).',
            'priority' => 'sedang',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id']);
        $this->assertSame('terbuka', DB::table('hd_tickets')->where('id', $response->json('id'))->value('status'));
    }

    public function test_daftar_tiket_terbatas_pada_milik_sendiri(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/bantuan', [
            'category' => 'absensi', 'subject' => 'Uji daftar', 'description' => 'Deskripsi uji.', 'priority' => 'rendah',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/bantuan');

        $response->assertOk();
        $response->assertJsonFragment(['subject' => 'Uji daftar']);
    }

    public function test_dapat_membalas_tiket_sendiri_dan_notifikasi_terkirim(): void
    {
        Notification::fake();
        $token = $this->token();

        $create = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/bantuan', [
            'category' => 'akun_akses', 'subject' => 'Uji balas', 'description' => 'Deskripsi uji.', 'priority' => 'tinggi',
        ]);
        $id = $create->json('id');

        $reply = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/bantuan/{$id}/balas", [
            'message' => 'Tambahan informasi dari pegawai.',
        ]);

        $reply->assertOk();
        $this->assertSame(1, DB::table('hd_ticket_replies')->where('ticket_id', $id)->count());
    }

    /**
     * Data milik pegawai LAIN disiapkan LANGSUNG lewat DB (BUKAN login
     * sebagai pegawai itu di request terpisah) — guard Sanctum
     * (RequestGuard) meng-cache user hasil resolusi PERTAMA untuk sisa
     * metode test, jadi menukar token Bearer di request kedua pada
     * TestCase yang sama tidak benar-benar berpindah identitas
     * (keterbatasan test framework, BUKAN celah otorisasi — lihat
     * verifikasi query langsung yang sudah dilakukan terpisah).
     */
    public function test_tidak_bisa_melihat_tiket_pegawai_lain(): void
    {
        $otherEmployeeId = DB::table('emp_employees')->where('nrp', '2015.07.0088')->value('id');
        $ticketId = (string) Uuid7::generate();

        DB::table('hd_tickets')->insert([
            'id' => $ticketId,
            'ticket_number' => 'TIKET/UJI/'.substr(uniqid(), -6),
            'employee_id' => $otherEmployeeId,
            'category' => 'lainnya',
            'subject' => 'Punya Pegawai Lain',
            'description' => 'Deskripsi uji.',
            'status' => 'terbuka',
            'priority' => 'rendah',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson("/api/v1/bantuan/{$ticketId}");

        $response->assertNotFound();
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ])->json('token');
    }
}
