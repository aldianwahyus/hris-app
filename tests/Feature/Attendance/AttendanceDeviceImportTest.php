<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Impor ekspor mesin fingerprint (SYSADMIN) — mengisi att_device_scan_logs
 * lalu langsung memicu SyncDeviceAttendance yang sudah ada.
 */
final class AttendanceDeviceImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_impor_csv_valid_tersinkron_menjadi_absensi(): void
    {
        $sitiId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id'); // PIN 1001, KC Mataram
        $officeId = DB::table('emp_employees')->where('id', $sitiId)->value('office_id');

        $csv = "pin,waktu\n1001,2026-09-10 08:15:00\n";
        $file = UploadedFile::fake()->createWithContent('mesin.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/absensi-mesin/impor', [
            'office_id' => $officeId,
            'berkas' => $file,
        ]);

        $response->assertRedirect(route('sysadmin.attendance-device.index'));
        $response->assertSessionHas('sukses');

        $record = DB::table('att_attendance_records')
            ->where('employee_id', $sitiId)->where('work_date', '2026-09-10')->first();

        $this->assertNotNull($record, 'Pindaian yang cocok dengan PIN 1001 harus tercatat sebagai absensi Siti.');
        $this->assertNotNull($record->check_in_at);
        $this->assertSame('fingerprint', $record->check_in_source);
    }

    public function test_pin_tidak_dikenal_tidak_membuat_baris_absensi(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');

        $csv = "pin,waktu\n9999,2026-09-10 08:15:00\n";
        $file = UploadedFile::fake()->createWithContent('mesin.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/absensi-mesin/impor', [
            'office_id' => $officeId,
            'berkas' => $file,
        ]);

        $response->assertSessionHas('sukses');

        $this->assertSame(
            0,
            DB::table('att_attendance_records')->whereDate('work_date', '2026-09-10')->count(),
            'PIN yang tidak terdaftar tidak boleh membuat baris absensi.'
        );

        $log = DB::table('att_device_scan_logs')->where('device_pin', '9999')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->processed_at, 'Tetap ditandai diproses meski tidak cocok, agar tidak diambil ulang.');
        $this->assertNull($log->employee_id);
    }

    public function test_baris_rusak_dilewati_baris_valid_tetap_diproses(): void
    {
        $sitiId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $officeId = DB::table('emp_employees')->where('id', $sitiId)->value('office_id');

        $csv = "pin,waktu\n,2026-09-10 08:15:00\n1001,tanggal-tidak-valid\n1001,2026-09-11 08:00:00\n";
        $file = UploadedFile::fake()->createWithContent('mesin.csv', $csv);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/absensi-mesin/impor', [
            'office_id' => $officeId,
            'berkas' => $file,
        ]);

        $response->assertSessionHas('sukses');
        $this->assertStringContainsString('2 baris dilewati', session('sukses'));

        $record = DB::table('att_attendance_records')
            ->where('employee_id', $sitiId)->where('work_date', '2026-09-11')->first();
        $this->assertNotNull($record, 'Baris valid di antara baris rusak tetap harus diproses.');
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/absensi-mesin');

        $response->assertForbidden();
    }

    private function sysAdmin(): User
    {
        return $this->userWithNrp('SYSADMIN');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
