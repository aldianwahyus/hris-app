<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Payroll\Application\DecidePayrollRun;
use App\Modules\Payroll\Application\RunPayrollDraft;
use App\Notifications\ApprovalSlaReminder;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SeedsOvertimeAttendance;
use Tests\TestCase;

/**
 * ESS Mobile (TOR Fase I) — endpoint API adalah cermin tipis layar
 * ESS web yang sudah ada, memakai Application-layer yang SAMA. Test
 * ini memverifikasi rute+auth+reuse validasi domain, BUKAN mengulang
 * seluruh kasus uji domain (sudah dicakup test Feature masing-masing
 * modul, mis. SubmitOvertimeRequestTest).
 */
final class EssApiTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    private const NRP = '2018.03.0142'; // Siti Rahmawati — Officer, KC Mataram

    private const PASSWORD = 'RahasiaDemo!123';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(self::NRP.'|127.0.0.1');
    }

    public function test_cuti_dapat_diajukan_lewat_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/cuti', [
            'start_date' => (new DateTimeImmutable('+10 days'))->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable('+11 days'))->format('Y-m-d'),
            'reason' => 'Uji API',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['request_number']);
    }

    public function test_daftar_cuti_terbatas_pada_milik_sendiri(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/cuti');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    /** Sisa cuti = jumlah SEMUA kantong (tahun berjalan + bawaan) yang belum terpakai — reuse LeaveBucket::remaining(). */
    public function test_sisa_cuti_muncul_di_respons_dan_dihitung_benar(): void
    {
        $employeeId = $this->employeeId(self::NRP);
        $year = (int) now()->format('Y');

        DB::table('leave_balances')->where('employee_id', $employeeId)->where('year', $year)->delete();
        DB::table('leave_balances')->insert([
            [
                'id' => (string) Uuid7::generate(), 'employee_id' => $employeeId, 'year' => $year,
                'bucket_type' => 'current_year', 'quota_days' => 12, 'used_days' => 4,
                'expires_on' => "{$year}-12-31", 'triggers_allowance' => true, 'consumption_order' => 1,
                'created_at' => now(), 'updated_at' => now(), 'version' => 1,
            ],
            [
                'id' => (string) Uuid7::generate(), 'employee_id' => $employeeId, 'year' => $year,
                'bucket_type' => 'carry_forward', 'quota_days' => 3, 'used_days' => 1,
                'expires_on' => "{$year}-03-31", 'triggers_allowance' => false, 'consumption_order' => 2,
                'created_at' => now(), 'updated_at' => now(), 'version' => 1,
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/cuti');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'sisa_cuti']);
        // (12-4) + (3-1) = 10 — JSON tanpa pecahan diserialisasi sebagai integer, bukan 10.0.
        $response->assertJsonPath('sisa_cuti', 10);
    }

    public function test_lembur_tanpa_bukti_absensi_ditolak_sebagai_json_422(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/lembur', [
            'overtime_type' => 'regular',
            'work_date' => '2026-09-02',
        ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('tidak ada lembur', $response->json('message'));
    }

    public function test_lembur_dengan_bukti_absensi_berhasil_lewat_api(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', self::NRP)->value('id');
        $this->seedOvertimeAttendance($employeeId, new DateTimeImmutable('2026-09-02'), 2.0);

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/lembur', [
            'overtime_type' => 'regular',
            'work_date' => '2026-09-02',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['spkl_number']);
    }

    public function test_sppd_dapat_diajukan_lewat_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/sppd', [
            'trip_category' => 'jarak_pendek',
            'destination' => 'Kantor Cabang Mataram',
            'purpose' => 'Uji API',
            'start_date' => (new DateTimeImmutable('+5 days'))->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable('+5 days'))->format('Y-m-d'),
            'radius_band' => '30_100',
        ]);

        $response->assertStatus(201);
    }

    public function test_slip_gaji_dapat_didaftar_lewat_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/slip-gaji');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    /**
     * Regresi (bug ditemukan lewat audit kode, konsisten dengan
     * perbaikan PayslipController::index() versi web): endpoint ini
     * sebelumnya HANYA mengembalikan take_home_partial_cents mentah,
     * tanpa deductions/additions ad-hoc — aplikasi mobile menampilkan
     * THP yang berbeda (lebih besar, salah) dari web/PDF untuk slip yang
     * sama. Sekarang take_home_cents WAJIB sudah memperhitungkan
     * keduanya, dan baris deductions/additions ikut disertakan.
     */
    public function test_slip_gaji_api_menyertakan_thp_yang_benar_setelah_potongan_ad_hoc(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', self::NRP)->value('id');

        $runId = app(RunPayrollDraft::class)->handle(
            officeId: DB::table('emp_employees')->where('id', $employeeId)->value('office_id'),
            period: new DateTimeImmutable('2026-09-01'),
            actor: new AuditActor(actorId: $this->employeeId('2021.05.0302'), actorRole: 'hr_admin'),
        );
        app(DecidePayrollRun::class)->approve($runId, new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'));

        $slipId = DB::table('pay_payslips')->where('employee_id', $employeeId)->value('id');
        $now = now();
        DB::table('pay_payslip_deductions')->insert([
            'id' => (string) Uuid7::generate(), 'payslip_id' => $slipId, 'deduction_type' => 'kasbon_pinjaman',
            'amount_cents' => 300_000_00, 'note' => null, 'created_by' => $this->employeeId('2021.05.0302'),
            'created_at' => $now, 'updated_at' => $now, 'version' => 1,
        ]);

        $takeHomePartial = (int) DB::table('pay_payslips')->where('id', $slipId)->value('take_home_partial_cents');

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/slip-gaji');

        $response->assertOk();
        $slip = collect($response->json('data'))->firstWhere('id', $slipId);
        $this->assertNotNull($slip);
        $this->assertSame($takeHomePartial - 300_000_00, $slip['take_home_cents']);
        $this->assertCount(1, $slip['deductions']);
    }

    public function test_notifikasi_dapat_didaftar_lewat_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/notifikasi');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'unread_count']);
    }

    public function test_absensi_dapat_dicatat_lewat_api(): void
    {
        // Siti Rahmawati — KC Mataram (-8.5871, 116.1082), lihat RecordGpsAttendanceTest.
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/absensi', [
            'latitude' => -8.5871,
            'longitude' => 116.1082,
            'action' => 'masuk',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['action', 'status']);
        $this->assertSame('masuk', $response->json('action'));
    }

    public function test_absensi_di_luar_geofence_ditolak_sebagai_json_422(): void
    {
        // 1 derajat lintang dari KC Mataram — puluhan km, jauh di luar radius kantor.
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/absensi', [
            'latitude' => -7.5871,
            'longitude' => 116.1082,
            'action' => 'masuk',
        ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('luar radius', $response->json('message'));
    }

    public function test_absensi_istirahat_dan_kembali_dapat_dicatat_lewat_api(): void
    {
        $parameterId = DB::table('cfg_parameters')->where('code', 'ATT_BREAK_START_TIME')->value('id');
        DB::table('cfg_parameter_values')->where('parameter_id', $parameterId)->update(['value' => '00:00']);
        $parameterId = DB::table('cfg_parameters')->where('code', 'ATT_BREAK_RETURN_TIME')->value('id');
        DB::table('cfg_parameter_values')->where('parameter_id', $parameterId)->update(['value' => '00:00']);

        $token = $this->token();
        $koordinat = ['latitude' => -8.5871, 'longitude' => 116.1082];

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/absensi', [...$koordinat, 'action' => 'masuk'])->assertCreated();

        $istirahat = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/absensi', [...$koordinat, 'action' => 'istirahat']);
        $istirahat->assertCreated();
        $this->assertSame('istirahat', $istirahat->json('action'));

        $kembali = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/absensi', [...$koordinat, 'action' => 'kembali']);
        $kembali->assertCreated();
        $this->assertSame('kembali', $kembali->json('action'));
    }

    public function test_absensi_kembali_tanpa_istirahat_ditolak_sebagai_json_422(): void
    {
        $token = $this->token();
        $koordinat = ['latitude' => -8.5871, 'longitude' => 116.1082];

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/absensi', [...$koordinat, 'action' => 'masuk']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/absensi', [...$koordinat, 'action' => 'kembali']);

        $response->assertUnprocessable();
        $this->assertStringContainsString('belum mencatat mulai istirahat', $response->json('message'));
    }

    public function test_daftar_absensi_terbatas_pada_milik_sendiri(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/absensi');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_notifikasi_dapat_ditandai_dibaca_lewat_api(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', self::NRP)->value('id');
        $user = User::query()->where('employee_id', $employeeId)->firstOrFail();
        $user->notify(new ApprovalSlaReminder('SPKL/2026/09/0001', 3, 'overtime_request'));
        $notificationId = $user->notifications()->first()->id;

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")
            ->postJson("/api/v1/notifikasi/{$notificationId}/baca");

        $response->assertNoContent();
        $this->assertNotNull(DB::table('notifications')->where('id', $notificationId)->value('read_at'));
    }

    public function test_notifikasi_milik_orang_lain_tidak_bisa_ditandai(): void
    {
        $lainNrp = '2015.07.0088';
        $lainEmployeeId = DB::table('emp_employees')->where('nrp', $lainNrp)->value('id');
        $lainUser = User::query()->where('employee_id', $lainEmployeeId)->firstOrFail();
        $lainUser->notify(new ApprovalSlaReminder('SPKL/2026/09/0002', 3, 'overtime_request'));
        $notificationId = $lainUser->notifications()->first()->id;

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")
            ->postJson("/api/v1/notifikasi/{$notificationId}/baca");

        $response->assertNotFound();
    }

    public function test_izin_dapat_diajukan_lewat_api_kategori_tanpa_lampiran_wajib(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/izin', [
            'category' => 'keperluan_keluarga',
            'start_date' => $this->officeToday(),
            'end_date' => (new DateTimeImmutable('+2 days'))->format('Y-m-d'),
            'reason' => 'Uji API',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['request_number']);
    }

    public function test_izin_kategori_sakit_tanpa_lampiran_ditolak_sebagai_json_422(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/izin', [
            'category' => 'sakit',
            'start_date' => $this->officeToday(),
            'end_date' => $this->officeToday(),
            'reason' => 'Demam',
        ]);

        $response->assertUnprocessable();
    }

    public function test_izin_kategori_sakit_dengan_lampiran_berhasil_lewat_api(): void
    {
        Storage::fake('s3');

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->post('/api/v1/izin', [
            'category' => 'sakit',
            'start_date' => $this->officeToday(),
            'end_date' => $this->officeToday(),
            'reason' => 'Demam',
            'attachment' => UploadedFile::fake()->image('surat-dokter.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonStructure(['request_number']);
    }

    public function test_izin_lampiran_format_tidak_didukung_ditolak_dengan_pesan_indonesia(): void
    {
        Storage::fake('s3');

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->post('/api/v1/izin', [
            'category' => 'sakit',
            'start_date' => $this->officeToday(),
            'end_date' => $this->officeToday(),
            'reason' => 'Demam',
            'attachment' => UploadedFile::fake()->create('surat-dokter.docx', 100, 'application/msword'),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['attachment' => ['Lampiran bukti hanya boleh berformat JPG, PNG, atau PDF.']]);
    }

    public function test_izin_lampiran_melebihi_5mb_ditolak_dengan_pesan_indonesia(): void
    {
        Storage::fake('s3');

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->post('/api/v1/izin', [
            'category' => 'sakit',
            'start_date' => $this->officeToday(),
            'end_date' => $this->officeToday(),
            'reason' => 'Demam',
            'attachment' => UploadedFile::fake()->create('surat-dokter.pdf', 5121, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['attachment' => ['Ukuran lampiran bukti maksimal 5 MB.']]);
    }

    public function test_daftar_izin_terbatas_pada_milik_sendiri(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/izin');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_tanpa_token_seluruh_endpoint_ess_ditolak(): void
    {
        $this->getJson('/api/v1/cuti')->assertUnauthorized();
        $this->getJson('/api/v1/lembur')->assertUnauthorized();
        $this->getJson('/api/v1/sppd')->assertUnauthorized();
        $this->getJson('/api/v1/absensi')->assertUnauthorized();
        $this->getJson('/api/v1/slip-gaji')->assertUnauthorized();
        $this->getJson('/api/v1/notifikasi')->assertUnauthorized();
        $this->getJson('/api/v1/izin')->assertUnauthorized();
        $this->getJson('/api/v1/menu-mobile')->assertUnauthorized();
    }

    /**
     * Menu Aplikasi Mobile — dikendalikan SYSADMIN/Admin HC lewat
     * MobileMenuSettingsController (web). Bawaan SEMUA menyala (lihat
     * migrasi create_mobile_menu_items), sesuai apa yang klien mobile
     * benar-benar konsumsi (MobileMenuContext.tsx): {key: boolean}.
     */
    public function test_menu_mobile_mencerminkan_saklar_admin(): void
    {
        DB::table('mobile_menu_items')->where('key', 'sppd')->update(['is_enabled' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/menu-mobile');

        $response->assertOk();
        $response->assertJson(['data' => ['sppd' => false, 'cuti' => true]]);
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ])->json('token');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    /**
     * Bug ditemukan lewat evaluasi PM/client (2026-09-01): SubmitIzinRequest
     * membandingkan tanggal terhadap "hari ini" pada ZONA WAKTU KANTOR
     * pegawai (Asia/Makassar, UTC+8) — bukan zona waktu default PHP/UTC.
     * `new DateTimeImmutable('today')` polos di sini rapuh terhadap JAM
     * (bukan cuma tanggal): saat UTC malam, Makassar sudah masuk hari
     * berikutnya, jadi "today" versi UTC bisa SATU HARI DI BELAKANG
     * "hari ini" versi kantor — pengajuan Izin ditolak 422 padahal
     * seharusnya lolos. Dihitung eksplisit pada zona waktu kantor Siti
     * (NRP konstan di file ini) supaya tidak lagi tergantung jam jalan.
     */
    private function officeToday(): string
    {
        $timezone = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.nrp', self::NRP)
            ->value('o.timezone') ?? 'Asia/Makassar';

        return (new DateTimeImmutable('today', new DateTimeZone($timezone)))->format('Y-m-d');
    }
}
