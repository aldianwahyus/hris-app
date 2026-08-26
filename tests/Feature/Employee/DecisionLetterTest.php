<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Employee\Application\DecideEmployeeProfileChange;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Surat Keputusan (SK) — modul TERSENDIRI (bukan bagian dari Data
 * Pegawai, lihat DecisionLetterController), HR-only, tulis LANGSUNG
 * tanpa persetujuan (pola sama modul HR-managed lain, lihat
 * ResolveEmployeeForHrAction), TAPI SK Mutasi/Promosi MEMICU pengajuan
 * perubahan data induk lewat SubmitEmployeeProfileChange yang SUDAH
 * ADA — pengajuan ITU SENDIRI tetap menunggu persetujuan hr_approver
 * seperti biasa (tidak ada kode baru untuk itu, cukup diverifikasi
 * keterhubungannya di sini). Satu form bisa berlaku untuk BANYAK
 * pegawai sekaligus lewat employee_ids[] (massal).
 */
final class DecisionLetterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_halaman_daftar_dan_buat_sk_dapat_dibuka_hr_admin(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');

        $index = $this->actingAs($rina)->get('/sk');
        $index->assertOk();

        $create = $this->actingAs($rina)->get('/sk/buat');
        $create->assertOk();
        $create->assertSee('cari-pegawai', false);
    }

    public function test_halaman_daftar_dan_buat_sk_dapat_dibuka_sysadmin(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');

        $index = $this->actingAs($sysadmin)->get('/sk');
        $index->assertOk();

        $create = $this->actingAs($sysadmin)->get('/sk/buat');
        $create->assertOk();
    }

    public function test_halaman_ubah_data_pegawai_tidak_lagi_menampilkan_seksi_sk(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');
        $targetId = $this->employeeId('2021.05.0302');

        $response = $this->actingAs($rina)->get("/pegawai/{$targetId}/ubah");

        $response->assertOk();
        // "Surat Keputusan" sendiri MUNCUL di sidebar (menu baru) — yang
        // diverifikasi di sini adalah bekas seksi/form SK yang dulu
        // tertanam di halaman ini sudah benar-benar hilang, bukan literal
        // teks itu (yang sekarang selalu ada di navigasi tiap halaman).
        $response->assertDontSee('sk_number', false);
        $response->assertDontSee('Tambah SK', false);
    }

    public function test_hr_admin_dapat_menambah_sk_sanksi_tanpa_memicu_perubahan_data_induk(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung
        $targetId = $this->employeeId('2021.05.0302'); // kantor sendiri (dia sendiri)

        $response = $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$targetId],
            'sk_type' => 'sanksi',
            'sk_number' => 'SK/001/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Teguran lisan — terlambat berulang kali.',
        ]);

        $response->assertRedirect(route('sk.index'));
        $response->assertSessionHas('sukses');

        $row = DB::table('emp_decision_letters')->where('employee_id', $targetId)->first();
        $this->assertNotNull($row);
        $this->assertSame('sanksi', $row->sk_type);
        $this->assertNull($row->profile_change_request_id);
        $this->assertSame(0, DB::table('emp_profile_change_requests')->where('employee_id', $targetId)->count());
    }

    public function test_sk_mutasi_memicu_pengajuan_perubahan_kantor_yang_menunggu_persetujuan(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');
        $targetId = $this->employeeId('2021.05.0302');
        $targetOfficeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');

        $response = $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$targetId],
            'sk_type' => 'mutasi',
            'sk_number' => 'SK/002/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Mutasi ke KC Mataram.',
            'target_office_id' => $targetOfficeId,
        ]);

        $response->assertRedirect(route('sk.index'));
        $response->assertSessionHas('sukses');
        $this->assertStringContainsString('menunggu persetujuan', session('sukses'));

        $sk = DB::table('emp_decision_letters')->where('employee_id', $targetId)->first();
        $this->assertNotNull($sk->profile_change_request_id);

        $pcr = DB::table('emp_profile_change_requests')->where('id', $sk->profile_change_request_id)->first();
        $this->assertNotNull($pcr);
        $this->assertSame('pending', $pcr->status);
        $this->assertSame($targetOfficeId, json_decode($pcr->proposed_changes, true)['office_id']);

        // emp_employees BELUM berubah — baru berubah setelah checker menyetujui.
        $this->assertNotSame($targetOfficeId, DB::table('emp_employees')->where('id', $targetId)->value('office_id'));
    }

    public function test_persetujuan_hr_approver_atas_pengajuan_dari_sk_benar_benar_mengubah_kantor_pegawai(): void
    {
        $rina = $this->userWithNrp('2021.05.0302');
        $targetId = $this->employeeId('2021.05.0302');
        $targetOfficeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');

        $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$targetId],
            'sk_type' => 'mutasi',
            'sk_number' => 'SK/003/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Mutasi ke KC Mataram.',
            'target_office_id' => $targetOfficeId,
        ]);

        $sk = DB::table('emp_decision_letters')->where('employee_id', $targetId)->first();

        app(DecideEmployeeProfileChange::class)->approve(
            $sk->profile_change_request_id,
            new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'),
        );

        $this->assertSame($targetOfficeId, DB::table('emp_employees')->where('id', $targetId)->value('office_id'));
    }

    public function test_unggah_berkas_sk_tersimpan_dan_dapat_diunduh(): void
    {
        Storage::fake('s3');

        $rina = $this->userWithNrp('2021.05.0302');
        $targetId = $this->employeeId('2021.05.0302');
        $file = UploadedFile::fake()->create('sk-scan.pdf', 100, 'application/pdf');

        $response = $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$targetId],
            'sk_type' => 'lainnya',
            'sk_number' => 'SK/004/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Uji unggah berkas.',
            'document' => $file,
        ]);

        $response->assertRedirect();

        $sk = DB::table('emp_decision_letters')->where('employee_id', $targetId)->first();
        $this->assertNotNull($sk->document_path);
        Storage::disk('s3')->assertExists($sk->document_path);

        $download = $this->actingAs($rina)->get("/sk/{$sk->id}/unduh");
        $download->assertOk();
    }

    public function test_hr_admin_ditolak_untuk_pegawai_kantor_lain(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung
        $sitiId = $this->employeeId('2018.03.0142'); // KC Mataram

        $response = $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$sitiId],
            'sk_type' => 'sanksi',
            'sk_number' => 'SK/005/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Uji.',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, DB::table('emp_decision_letters')->where('employee_id', $sitiId)->count());
    }

    public function test_sysadmin_dapat_menambah_sk_pegawai_kantor_mana_pun(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($sysadmin)->post('/sk', [
            'employee_ids' => [$sitiId],
            'sk_type' => 'sanksi',
            'sk_number' => 'SK/006/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Uji.',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, DB::table('emp_decision_letters')->where('employee_id', $sitiId)->count());
    }

    public function test_pegawai_dapat_melihat_riwayat_sk_miliknya_sendiri_di_cv_saya(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $sitiId = $this->employeeId('2018.03.0142');

        DB::table('emp_decision_letters')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $sitiId,
            'sk_type' => 'sanksi',
            'sk_number' => 'SK/007/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Uji.',
            'created_by' => $sitiId,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->actingAs($siti)->get('/cv-saya');

        $response->assertOk();
        $response->assertSee('SK/007/2026');
    }

    public function test_pegawai_tidak_bisa_mengunduh_berkas_sk_pegawai_lain(): void
    {
        Storage::fake('s3');

        $rina = $this->userWithNrp('2021.05.0302');
        $targetId = $this->employeeId('2021.05.0302');
        $file = UploadedFile::fake()->create('sk-scan.pdf', 100, 'application/pdf');

        $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$targetId],
            'sk_type' => 'lainnya',
            'sk_number' => 'SK/008/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Uji.',
            'document' => $file,
        ]);

        $sk = DB::table('emp_decision_letters')->where('employee_id', $targetId)->first();
        $siti = $this->userWithNrp('2018.03.0142'); // bukan pemilik SK ini

        $response = $this->actingAs($siti)->get("/cv-saya/sk/{$sk->id}/unduh");

        $response->assertNotFound();
    }

    /** Fase III (modul mandiri) — SK massal: satu form, banyak pegawai sekaligus. */
    public function test_hr_admin_dapat_membuat_sk_untuk_banyak_pegawai_sekaligus(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        // Dua pegawai KANTOR PUSAT (SYSADMIN bank-wide, jadi bebas pilih siapa saja) —
        // yang penting kedua ID beda, keduanya menerima SK+pengajuan Mutasi yang SAMA.
        $employeeIdA = $this->employeeId('2018.03.0142');
        $employeeIdB = $this->employeeId('2017.11.0119');
        $targetOfficeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');

        $response = $this->actingAs($sysadmin)->post('/sk', [
            'employee_ids' => [$employeeIdA, $employeeIdB],
            'sk_type' => 'mutasi',
            'sk_number' => 'SK/009/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Mutasi bersama ke KC Mataram.',
            'target_office_id' => $targetOfficeId,
        ]);

        $response->assertRedirect(route('sk.index'));
        $response->assertSessionHas('sukses');
        $this->assertStringContainsString('2 dari 2 pegawai', session('sukses'));

        $rows = DB::table('emp_decision_letters')->where('sk_number', 'SK/009/2026')->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing([$employeeIdA, $employeeIdB], $rows->pluck('employee_id')->all());

        // Setiap pegawai punya pengajuan perubahan data induk SENDIRI-SENDIRI
        // (emp_profile_change_requests tidak punya konsep "satu pengajuan
        // untuk banyak pegawai").
        $requestIds = $rows->pluck('profile_change_request_id')->filter()->unique();
        $this->assertCount(2, $requestIds);
        $this->assertSame(2, DB::table('emp_profile_change_requests')->whereIn('id', $requestIds)->where('status', 'pending')->count());
    }

    public function test_sk_massal_ditolak_total_kalau_satu_pegawai_di_luar_lingkup(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP Gerung
        $ownId = $this->employeeId('2021.05.0302'); // kantor sendiri
        $sitiId = $this->employeeId('2018.03.0142'); // KC Mataram — di luar lingkup

        $response = $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$ownId, $sitiId],
            'sk_type' => 'sanksi',
            'sk_number' => 'SK/010/2026',
            'sk_date' => '2026-01-01',
            'description' => 'Uji.',
        ]);

        $response->assertForbidden();
        // Lingkup divalidasi SEBELUM baris mana pun disimpan — gagal total,
        // bukan separuh jalan (lihat komentar DecisionLetterController::store()).
        $this->assertSame(0, DB::table('emp_decision_letters')->where('sk_number', 'SK/010/2026')->count());
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
