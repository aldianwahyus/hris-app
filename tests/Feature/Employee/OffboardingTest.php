<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Offboarding — modul baru (evaluasi PM/client 2026-09-02).
 * Maker-checker pola PERSIS pegawai baru (emp_new_employee_requests):
 * hr_admin lingkup kantornya sendiri (baik sebagai maker MAUPUN saat
 * membuka detail), hr_approver seluruh bank. Disetujui → checklist
 * clearance otomatis (item standar + aset aktif dari Modul 1).
 * Login pegawai yang sudah dituntaskan pemisahannya harus ditolak.
 */
final class OffboardingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('2021.05.0302|127.0.0.1');
        RateLimiter::clear('account|2021.05.0302');
    }

    public function test_hr_admin_tidak_bisa_mengajukan_pemisahan_pegawai_kantor_lain(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina — hr_admin KCP Gerung
        $siti = $this->employeeId('2018.03.0142'); // kantor lain

        $response = $this->actingAs($hrAdmin)->post('/persetujuan/offboarding/buat', [
            'employee_id' => $siti,
            'separation_type' => 'resign',
            'reason' => 'Uji lingkup kantor.',
            'requested_last_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, DB::table('off_separation_requests')->where('employee_id', $siti)->count());
    }

    public function test_alur_lengkap_pemisahan_sampai_akun_dinonaktifkan(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina, mengajukan pemisahan DIRINYA SENDIRI
        $hrAdminId = $this->employeeId('2021.05.0302');
        $hrApprover = $this->userWithNrp('2014.02.0061'); // Nur Aisyah, memutuskan

        // Kata sandi DIKETAHUI supaya penolakan login pasca-pemisahan bisa
        // dibuktikan memakai kredensial yang BENAR (bukan sekadar kata
        // sandi salah, yang akan gagal login terlepas dari fitur ini).
        DB::table('users')->where('employee_id', $hrAdminId)->update(['password' => Hash::make('RahasiaUjiOffboarding!1')]);
        $this->post('/masuk', ['nrp' => '2021.05.0302', 'password' => 'RahasiaUjiOffboarding!1'])->assertRedirect();
        $this->post('/keluar');

        // Tugaskan satu aset ke Rina lebih dulu — memverifikasi integrasi
        // Modul 1: clearance HARUS memuat item "Kembalikan: {aset}".
        $assetId = $this->createAsset();
        $this->actingAs($this->userWithNrp('SYSADMIN'))->post("/admin/sistem/aset/{$assetId}/tugaskan", ['employee_id' => $hrAdminId]);

        $store = $this->actingAs($hrAdmin)->post('/persetujuan/offboarding/buat', [
            'employee_id' => $hrAdminId,
            'separation_type' => 'resign',
            'reason' => 'Mengundurkan diri untuk peluang lain.',
            'requested_last_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $separationId = DB::table('off_separation_requests')->where('employee_id', $hrAdminId)->value('id');
        $this->assertNotNull($separationId);
        $store->assertRedirect(route('admin.offboarding-show', $separationId));

        // hr_approver tidak bisa memutuskan pengajuannya sendiri — di sini
        // Nur Aisyah BUKAN pengaju, jadi harus berhasil.
        $approve = $this->actingAs($hrApprover)->post("/persetujuan/offboarding/{$separationId}/setujui");
        $approve->assertRedirect(route('admin.offboarding-show', $separationId));
        $this->assertSame('approved', DB::table('off_separation_requests')->where('id', $separationId)->value('status'));

        $items = DB::table('off_clearance_items')->where('separation_id', $separationId)->get();
        $this->assertGreaterThanOrEqual(5, $items->count()); // 4 item standar + 1 item aset
        $this->assertTrue($items->contains(fn ($i) => str_starts_with($i->item_name, 'Kembalikan:') && $i->category === 'aset'));

        // Belum bisa dituntaskan selagi masih ada item belum selesai.
        $premature = $this->actingAs($hrApprover)->post("/persetujuan/offboarding/{$separationId}/tuntaskan");
        $premature->assertSessionHas('gagal');
        $this->assertNull(DB::table('emp_employees')->where('id', $hrAdminId)->value('separated_at'));

        foreach ($items as $item) {
            $this->actingAs($hrApprover)->post("/persetujuan/offboarding/{$separationId}/item/{$item->id}", ['is_done' => '1']);
        }

        $complete = $this->actingAs($hrApprover)->post("/persetujuan/offboarding/{$separationId}/tuntaskan");
        $complete->assertRedirect(route('admin.offboarding-show', $separationId));
        $this->assertSame('selesai', DB::table('off_separation_requests')->where('id', $separationId)->value('status'));
        $this->assertNotNull(DB::table('emp_employees')->where('id', $hrAdminId)->value('separated_at'));

        // Login pegawai yang sudah dipisahkan HARUS ditolak WALAUPUN kata
        // sandinya BENAR — pesan seragam dengan kredensial salah (lihat
        // catatan anti-enumerasi di AuthenticateEmployee). Logout eksplisit
        // dulu — panggilan actingAs() sebelumnya (hrApprover) masih
        // membekas di guard Auth, sehingga /masuk tanpa ini akan dianggap
        // sudah masuk dan dialihkan TANPA menjalankan validasi kredensial.
        $this->post('/keluar');
        $this->post('/masuk', ['nrp' => '2021.05.0302', 'password' => 'RahasiaUjiOffboarding!1'])
            ->assertSessionHasErrors('nrp');
    }

    public function test_hr_approver_tidak_bisa_menyetujui_pengajuan_pemisahan_miliknya_sendiri(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $hrApproverId = $this->employeeId('2014.02.0061');

        $this->actingAs($hrApprover)->post('/persetujuan/offboarding/buat', [
            'employee_id' => $hrApproverId,
            'separation_type' => 'resign',
            'reason' => 'Uji tolak swa-setuju.',
            'requested_last_date' => now()->addDays(14)->format('Y-m-d'),
        ]);
        $separationId = DB::table('off_separation_requests')->where('employee_id', $hrApproverId)->value('id');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/offboarding/{$separationId}/setujui");

        $response->assertSessionHas('gagal');
        $this->assertSame('pending', DB::table('off_separation_requests')->where('id', $separationId)->value('status'));
    }

    public function test_pegawai_dapat_mengisi_wawancara_keluar_setelah_disetujui(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina, mengajukan sekaligus MENGISI wawancara keluarnya sendiri
        $hrAdminId = $this->employeeId('2021.05.0302');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $this->actingAs($hrAdmin)->post('/persetujuan/offboarding/buat', [
            'employee_id' => $hrAdminId,
            'separation_type' => 'resign',
            'reason' => 'Uji wawancara keluar.',
            'requested_last_date' => now()->addDays(14)->format('Y-m-d'),
        ]);
        $separationId = DB::table('off_separation_requests')->where('employee_id', $hrAdminId)->value('id');
        $this->actingAs($hrApprover)->post("/persetujuan/offboarding/{$separationId}/setujui");

        $form = $this->actingAs($hrAdmin)->get('/wawancara-keluar');
        $form->assertOk();

        $submit = $this->actingAs($hrAdmin)->post('/wawancara-keluar', [
            'satisfaction_rating' => 4,
            'would_recommend' => '1',
            'comments' => 'Pengalaman kerja baik secara umum.',
        ]);
        $submit->assertRedirect(route('ess.dashboard'));

        $interview = DB::table('off_exit_interviews')->where('separation_id', $separationId)->first();
        $this->assertNotNull($interview);
        $this->assertSame(4, $interview->satisfaction_rating);

        // Tidak bisa mengisi dua kali.
        $again = $this->actingAs($hrAdmin)->get('/wawancara-keluar');
        $again->assertForbidden();
    }

    public function test_pemisahan_yang_ditolak_tidak_membangkitkan_clearance(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina, mengajukan pemisahan dirinya sendiri
        $hrAdminId = $this->employeeId('2021.05.0302');
        $hrApprover = $this->userWithNrp('2014.02.0061'); // Nur Aisyah, memutuskan (bank-wide)

        $this->actingAs($hrAdmin)->post('/persetujuan/offboarding/buat', [
            'employee_id' => $hrAdminId,
            'separation_type' => 'phk',
            'reason' => 'Uji penolakan.',
            'requested_last_date' => now()->addDays(7)->format('Y-m-d'),
        ]);
        $separationId = DB::table('off_separation_requests')->where('employee_id', $hrAdminId)->value('id');

        $reject = $this->actingAs($hrApprover)
            ->post("/persetujuan/offboarding/{$separationId}/tolak", ['catatan' => 'Tidak memenuhi syarat.']);

        $reject->assertRedirect(route('admin.offboarding-index'));
        $this->assertSame('rejected', DB::table('off_separation_requests')->where('id', $separationId)->value('status'));
        $this->assertSame(0, DB::table('off_clearance_items')->where('separation_id', $separationId)->count());
        $this->assertNull(DB::table('emp_employees')->where('id', $hrAdminId)->value('separated_at'));
    }

    private function createAsset(): string
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $officeId = DB::table('md_offices')->value('id');
        $code = 'LT-OFFB-'.uniqid();

        $this->actingAs($sysAdmin)->post('/admin/sistem/aset', [
            'asset_code' => $code, 'name' => 'Laptop Uji Offboarding', 'category' => 'Laptop', 'office_id' => $officeId,
        ]);

        return DB::table('ast_assets')->where('asset_code', $code)->value('id');
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
