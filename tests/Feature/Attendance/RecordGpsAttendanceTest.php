<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Absen GPS dari ESS. Status hadir/telat sengaja TIDAK diuji di sini —
 * bergantung jam nyata saat pengujian berjalan (lihat AttendanceDayPolicyTest
 * untuk itu, yang mengontrol waktu penuh). Berkas ini menguji perilaku yang
 * TIDAK bergantung jam: validasi radius, urutan masuk/istirahat/kembali/
 * pulang, kepemilikan.
 *
 * Jendela waktu Istirahat/Kembali (ATT_BREAK_START_TIME/ATT_BREAK_RETURN_TIME)
 * JUGA bergantung jam nyata — pengujian jalur SUKSES/GAGAL berbasis waktu
 * di sini memakai trik override nilai parameter (bukan mengontrol jam
 * sistem, yang tidak dibaca RecordGpsAttendance lewat Carbon::setTestNow()
 * sama sekali — ia memakai `new DateTimeImmutable('now', ...)` mentah):
 * set ke "00:00" supaya SELALU lolos apa pun jam sungguhan saat ini, atau
 * "23:59" supaya SELALU gagal — deterministik tanpa bergantung kapan test
 * ini dijalankan. Logika ambang itu sendiri diuji murni & pasti di
 * AttendanceBreakPolicyTest (Unit), bukan di sini.
 */
final class RecordGpsAttendanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_absen_masuk_di_titik_kantor_berhasil(): void
    {
        // Siti Rahmawati — KC Mataram (-8.5871, 116.1082).
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post('/absensi', ['latitude' => -8.5871, 'longitude' => 116.1082, 'action' => 'masuk']);

        $response->assertRedirect(route('attendance.create'));
        $response->assertSessionHas('sukses');

        $employeeId = $this->employeeId('2018.03.0142');
        $record = DB::table('att_attendance_records')->where('employee_id', $employeeId)->first();

        $this->assertNotNull($record);
        $this->assertNotNull($record->check_in_at);
        $this->assertSame('gps', $record->check_in_source);
        $this->assertNull($record->check_out_at);
    }

    public function test_absen_jauh_di_luar_radius_ditolak(): void
    {
        // 1 derajat lintang dari KC Mataram — puluhan km, jauh di luar radius 150m.
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post('/absensi', ['latitude' => -7.5871, 'longitude' => 116.1082, 'action' => 'masuk']);

        $response->assertRedirect(route('attendance.create'));
        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('luar radius', session('gagal'));

        $employeeId = $this->employeeId('2018.03.0142');
        $this->assertNull(DB::table('att_attendance_records')->where('employee_id', $employeeId)->first());
    }

    /** Istirahat/Kembali OPSIONAL — langsung Masuk→Pulang tetap sah, pola lama tidak rusak. */
    public function test_pulang_langsung_tanpa_istirahat_tetap_sah(): void
    {
        $user = $this->userWithNrp('2019.09.0177'); // Dewi Lestari — KC Selong (-8.6500, 116.5333)
        $koordinat = ['latitude' => -8.6500, 'longitude' => 116.5333];

        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'masuk']);
        $response = $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'pulang']);

        $response->assertSessionHas('sukses');
        $this->assertStringContainsString('pulang', session('sukses'));

        $record = DB::table('att_attendance_records')->where('employee_id', $user->employee_id)->first();
        $this->assertNotNull($record->check_in_at);
        $this->assertNotNull($record->check_out_at);
        $this->assertSame('gps', $record->check_out_source);
        $this->assertNull($record->break_start_at);
    }

    public function test_absen_pulang_kedua_kali_pada_hari_yang_sama_ditolak(): void
    {
        $user = $this->userWithNrp('2017.11.0119'); // Hendra Wijaya — KC Mataram
        $koordinat = ['latitude' => -8.5871, 'longitude' => 116.1082];

        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'masuk']);
        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'pulang']);
        $response = $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'pulang']);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('sudah lengkap', session('gagal'));
    }

    public function test_lintang_di_luar_rentang_valid_ditolak_validasi(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post('/absensi', ['latitude' => 200, 'longitude' => 116.1082, 'action' => 'masuk']);

        $response->assertSessionHasErrors('latitude');
    }

    public function test_aksi_tidak_dikenal_ditolak_validasi(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post('/absensi', ['latitude' => -8.5871, 'longitude' => 116.1082, 'action' => 'tidur-siang']);

        $response->assertSessionHasErrors('action');
    }

    public function test_istirahat_sebelum_masuk_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post('/absensi', ['latitude' => -8.5871, 'longitude' => 116.1082, 'action' => 'istirahat']);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('belum absen masuk', session('gagal'));
    }

    public function test_kembali_tanpa_istirahat_ditolak(): void
    {
        $user = $this->userWithNrp('2018.03.0142');
        $koordinat = ['latitude' => -8.5871, 'longitude' => 116.1082];

        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'masuk']);
        $response = $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'kembali']);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('belum mencatat mulai istirahat', session('gagal'));
    }

    public function test_pulang_diblokir_selama_masih_tercatat_istirahat(): void
    {
        $user = $this->userWithNrp('2018.03.0142');
        $employeeId = $user->employee_id;
        $koordinat = ['latitude' => -8.5871, 'longitude' => 116.1082];

        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'masuk']);

        // break_start_at diisi LANGSUNG lewat DB (bukan lewat aksi
        // 'istirahat' sungguhan) — menghindari ketergantungan pada jam
        // sistem nyata saat ini terhadap ATT_BREAK_START_TIME, murni
        // menguji penjagaan URUTAN (pulang diblokir selama masih
        // istirahat), bukan jendela waktu itu sendiri.
        DB::table('att_attendance_records')->where('employee_id', $employeeId)->update([
            'break_start_at' => now(),
            'break_start_source' => 'gps',
        ]);

        $response = $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'pulang']);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('Kembali', session('gagal'));
        $this->assertNull(DB::table('att_attendance_records')->where('employee_id', $employeeId)->value('check_out_at'));
    }

    public function test_istirahat_sebelum_jam_yang_diizinkan_ditolak(): void
    {
        $this->overrideBreakParameter('ATT_BREAK_START_TIME', '23:59'); // selalu di masa depan dari jam nyata mana pun

        $user = $this->userWithNrp('2018.03.0142');
        $koordinat = ['latitude' => -8.5871, 'longitude' => 116.1082];

        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'masuk']);
        $response = $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'istirahat']);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('23:59', session('gagal'));
        $this->assertNull(DB::table('att_attendance_records')->where('employee_id', $user->employee_id)->value('break_start_at'));
    }

    public function test_alur_lengkap_masuk_istirahat_kembali_pulang_berhasil(): void
    {
        // "00:00" selalu sudah lewat pada jam nyata berapa pun — jalur
        // sukses ini deterministik terlepas kapan test dijalankan.
        $this->overrideBreakParameter('ATT_BREAK_START_TIME', '00:00');
        $this->overrideBreakParameter('ATT_BREAK_RETURN_TIME', '00:00');

        $user = $this->userWithNrp('2018.03.0142');
        $employeeId = $user->employee_id;
        $koordinat = ['latitude' => -8.5871, 'longitude' => 116.1082];

        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'masuk'])->assertSessionHas('sukses');
        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'istirahat'])->assertSessionHas('sukses');
        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'kembali'])->assertSessionHas('sukses');
        $this->actingAs($user)->post('/absensi', [...$koordinat, 'action' => 'pulang'])->assertSessionHas('sukses');

        $record = DB::table('att_attendance_records')->where('employee_id', $employeeId)->first();
        $this->assertNotNull($record->check_in_at);
        $this->assertNotNull($record->break_start_at);
        $this->assertNotNull($record->break_end_at);
        $this->assertNotNull($record->check_out_at);
    }

    private function overrideBreakParameter(string $code, string $value): void
    {
        $parameterId = DB::table('cfg_parameters')->where('code', $code)->value('id');
        DB::table('cfg_parameter_values')->where('parameter_id', $parameterId)->update(['value' => $value]);
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
