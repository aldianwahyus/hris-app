<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sinkronisasi mesin fingerprint (php artisan absensi:sinkronkan-mesin).
 * Waktu pindai SENGAJA ditulis eksplisit dalam zona kantor pegawai —
 * berbeda dengan RecordGpsAttendanceTest, ini membuat hasil pengujian
 * (hadir/telat, urutan masuk/pulang) sepenuhnya deterministik.
 *
 * Dipakukan pada HARI INI (bukan tanggal tetap di masa lalu) agar tidak
 * pernah jatuh di luar jendela mundur 2 hari milik SyncAttendanceDevice,
 * berapa pun tanggal sungguhan saat pengujian dijalankan.
 */
final class SyncAttendanceDeviceTest extends TestCase
{
    use DatabaseTransactions;

    private function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar')))->format('Y-m-d');
    }

    public function test_pindai_pertama_dicatat_sebagai_masuk(): void
    {
        // Hendra Wijaya — pin 1006 (2026_01_06_000003_seed_attendance_demo_data).
        $logId = $this->insertScan('1006', '07:50:00');

        $this->artisan('absensi:sinkronkan-mesin')->assertExitCode(0);

        $employeeId = DB::table('emp_employees')->where('nrp', '2017.11.0119')->value('id');
        $record = DB::table('att_attendance_records')
            ->where('employee_id', $employeeId)->where('work_date', $this->today())->first();

        $this->assertNotNull($record);
        $this->assertSame('fingerprint', $record->check_in_source);
        $this->assertSame('hadir', $record->status); // 07:50 < 08:00+15

        $log = DB::table('att_device_scan_logs')->where('id', $logId)->first();
        $this->assertNotNull($log->processed_at);
        $this->assertSame($employeeId, $log->employee_id);
    }

    public function test_pindai_terlambat_dicatat_telat(): void
    {
        $this->insertScan('1001', '09:00:00'); // Siti Rahmawati

        $this->artisan('absensi:sinkronkan-mesin');

        $employeeId = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('id');
        $record = DB::table('att_attendance_records')
            ->where('employee_id', $employeeId)->where('work_date', $this->today())->first();

        $this->assertSame('telat', $record->status);
    }

    public function test_pindai_kedua_pada_hari_yang_sama_dicatat_sebagai_pulang(): void
    {
        $this->insertScan('1003', '07:55:00'); // Budi Santoso
        $this->insertScan('1003', '17:10:00');

        $this->artisan('absensi:sinkronkan-mesin');

        $employeeId = DB::table('emp_employees')->where('nrp', '2020.01.0231')->value('id');
        $record = DB::table('att_attendance_records')
            ->where('employee_id', $employeeId)->where('work_date', $this->today())->first();

        $this->assertNotNull($record->check_in_at);
        $this->assertNotNull($record->check_out_at);
        $this->assertSame('fingerprint', $record->check_out_source);
    }

    public function test_pin_tidak_dikenal_diproses_tanpa_membuat_catatan_kehadiran(): void
    {
        $logId = $this->insertScan('9999', '08:00:00');

        $this->artisan('absensi:sinkronkan-mesin')->assertExitCode(0);

        $log = DB::table('att_device_scan_logs')->where('id', $logId)->first();
        $this->assertNotNull($log->processed_at, 'Pindai tak dikenal tetap harus ditandai diproses agar tidak diulang.');
        $this->assertNull($log->employee_id);
    }

    public function test_pindai_yang_sudah_diproses_tidak_diproses_ulang(): void
    {
        $this->insertScan('1006', '07:50:00');
        $this->artisan('absensi:sinkronkan-mesin');

        $employeeId = DB::table('emp_employees')->where('nrp', '2017.11.0119')->value('id');
        $checkInPertama = DB::table('att_attendance_records')
            ->where('employee_id', $employeeId)->where('work_date', $this->today())->value('check_in_at');

        // Jalankan lagi tanpa pindai baru — tidak boleh ada yang berubah.
        $this->artisan('absensi:sinkronkan-mesin');

        $checkInKedua = DB::table('att_attendance_records')
            ->where('employee_id', $employeeId)->where('work_date', $this->today())->value('check_in_at');

        $this->assertSame($checkInPertama, $checkInKedua);
        // Dihitung per employee_id + work_date (bukan seluruh riwayat
        // pegawai): data contoh migrasi (2026_01_06_000003) turut
        // menyisipkan satu pindai lain untuk pin yang sama pada tanggal
        // migrasi dijalankan — baris berbeda tanggal itu memang SAH,
        // bukan duplikasi yang sedang diuji di sini.
        $this->assertSame(
            1,
            DB::table('att_attendance_records')
                ->where('employee_id', $employeeId)
                ->where('work_date', $this->today())
                ->count(),
        );
    }

    private function insertScan(string $pin, string $timeOfDay): string
    {
        $id = (string) Uuid7::generate();

        // Kolom timestamptz harus ditulis UTC eksplisit — lihat catatan
        // di SyncDeviceAttendance::applyScan tentang mengapa mengirim
        // objek DateTimeImmutable berzona Asia/Makassar langsung akan
        // tergeser saat diformat tanpa offset oleh Laravel.
        $scannedAtUtc = (new DateTimeImmutable("today {$timeOfDay}", new DateTimeZone('Asia/Makassar')))
            ->setTimezone(new DateTimeZone('UTC'));

        DB::table('att_device_scan_logs')->insert([
            'id' => $id,
            'device_pin' => $pin,
            'scanned_at' => $scannedAtUtc,
            'employee_id' => null,
            'processed_at' => null,
            'raw_payload' => null,
            'created_at' => now(),
        ]);

        return $id;
    }
}
