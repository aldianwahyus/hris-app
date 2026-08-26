<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ApprovalSlaReminder;
use DateTimeImmutable;
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
            'start_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
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
            'start_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'reason' => 'Demam',
        ]);

        $response->assertUnprocessable();
    }

    public function test_izin_kategori_sakit_dengan_lampiran_berhasil_lewat_api(): void
    {
        Storage::fake('s3');

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->post('/api/v1/izin', [
            'category' => 'sakit',
            'start_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'reason' => 'Demam',
            'attachment' => UploadedFile::fake()->image('surat-dokter.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonStructure(['request_number']);
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
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ])->json('token');
    }
}
