<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Notifications\TicketReplied;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * HR Helpdesk / Case Management — modul baru (evaluasi PM/client
 * 2026-09-02). SATU tahap, dua arah balasan (pola PERSIS Layanan
 * Dokumen Mandiri): hr_admin lingkup kantornya sendiri, hr_approver
 * seluruh bank.
 */
final class HelpdeskTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_mengajukan_tiket_dan_melihat_riwayat(): void
    {
        $pegawai = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($pegawai)->post('/bantuan/ajukan', [
            'category' => 'penggajian',
            'priority' => 'sedang',
            'subject' => 'Slip gaji belum muncul',
            'description' => 'Slip gaji bulan ini belum bisa diunduh.',
        ]);

        $ticket = DB::table('hd_tickets')->where('employee_id', $pegawai->employee_id)->first();
        $this->assertNotNull($ticket);
        $this->assertSame('terbuka', $ticket->status);
        $response->assertRedirect(route('helpdesk.show', $ticket->id));

        $history = $this->actingAs($pegawai)->get('/bantuan');
        $history->assertOk();
        $history->assertSeeText('Slip gaji belum muncul');
    }

    public function test_pegawai_tidak_bisa_melihat_tiket_pegawai_lain(): void
    {
        $ticketId = $this->submitTicket('2018.03.0142');
        $lainnya = $this->userWithNrp('2021.05.0302');

        $response = $this->actingAs($lainnya)->get("/bantuan/{$ticketId}");

        $response->assertNotFound();
    }

    public function test_hr_admin_hanya_melihat_tiket_kantornya_sendiri(): void
    {
        $this->submitTicket('2021.05.0302'); // Rina — hr_admin KCP Gerung, mengajukan sendiri
        $this->submitTicket('2018.03.0142'); // Siti — kantor lain

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/persetujuan/bantuan');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertDontSeeText('Siti Rahmawati');
    }

    public function test_hc_membalas_dan_pegawai_menerima_notifikasi(): void
    {
        Notification::fake();
        $ticketId = $this->submitTicket('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/bantuan/{$ticketId}/balas", [
            'message' => 'Sudah kami cek, akan segera diterbitkan.',
        ]);

        $response->assertRedirect(route('admin.helpdesk-show', $ticketId));
        $this->assertSame(1, DB::table('hd_ticket_replies')->where('ticket_id', $ticketId)->where('is_internal_note', false)->count());

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (TicketReplied $n) => str_contains($n->message, 'Sudah kami cek, akan segera diterbitkan.'),
        );
    }

    public function test_catatan_internal_tidak_terlihat_pegawai_dan_tidak_mengirim_notifikasi(): void
    {
        Notification::fake();
        $ticketId = $this->submitTicket('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $this->actingAs($hrApprover)->post("/persetujuan/bantuan/{$ticketId}/balas", [
            'message' => 'Catatan internal: tunggu konfirmasi payroll.',
            'is_internal_note' => '1',
        ]);

        $pegawai = $this->userWithNrp('2018.03.0142');
        $show = $this->actingAs($pegawai)->get("/bantuan/{$ticketId}");
        $show->assertDontSeeText('Catatan internal: tunggu konfirmasi payroll.');

        Notification::assertNotSentTo($pegawai, TicketReplied::class);
    }

    public function test_pegawai_membalas_tiket_selesai_membuka_lagi_status_diproses(): void
    {
        $ticketId = $this->submitTicket('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/bantuan/{$ticketId}/status", ['status' => 'selesai']);
        $this->assertSame('selesai', DB::table('hd_tickets')->where('id', $ticketId)->value('status'));

        $pegawai = $this->userWithNrp('2018.03.0142');
        $this->actingAs($pegawai)->post("/bantuan/{$ticketId}/balas", ['message' => 'Masih belum bisa, mohon dicek lagi.']);

        $this->assertSame('diproses', DB::table('hd_tickets')->where('id', $ticketId)->value('status'));
    }

    public function test_hc_dapat_menugaskan_tiket(): void
    {
        $ticketId = $this->submitTicket('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $penugasanId = $this->employeeId('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/bantuan/{$ticketId}/tugaskan", [
            'assigned_to' => $penugasanId,
        ]);

        $response->assertRedirect(route('admin.helpdesk-show', $ticketId));
        $this->assertSame($penugasanId, DB::table('hd_tickets')->where('id', $ticketId)->value('assigned_to'));
    }

    public function test_tiket_ditutup_tidak_bisa_dibalas(): void
    {
        $ticketId = $this->submitTicket('2018.03.0142');
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/bantuan/{$ticketId}/status", ['status' => 'ditutup']);

        $pegawai = $this->userWithNrp('2018.03.0142');
        $response = $this->actingAs($pegawai)->post("/bantuan/{$ticketId}/balas", ['message' => 'Halo?']);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('hd_ticket_replies')->where('ticket_id', $ticketId)->count());
    }

    private function submitTicket(string $nrp): string
    {
        $pegawai = $this->userWithNrp($nrp);

        $this->actingAs($pegawai)->post('/bantuan/ajukan', [
            'category' => 'lainnya',
            'priority' => 'sedang',
            'subject' => 'Tiket uji otomatis',
            'description' => 'Deskripsi tiket uji otomatis.',
        ]);

        return DB::table('hd_tickets')->where('employee_id', $pegawai->employee_id)->latest('created_at')->value('id');
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
