<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Application\ImportAttendanceDeviceScans;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Test lapisan aplikasi (bukan PHPUnit murni) karena
 * ImportAttendanceDeviceScans menulis ke att_device_scan_logs — butuh
 * basis data sungguhan, tapi fokus di sini pada parser (header
 * fleksibel, baris rusak), bukan alur HTTP (lihat
 * tests/Feature/Attendance/AttendanceDeviceImportTest.php untuk itu).
 */
final class ImportAttendanceDeviceScansTest extends TestCase
{
    use DatabaseTransactions;

    public function test_header_huruf_besar_dan_nama_kolom_alternatif_diterima(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');
        $path = $this->writeTempCsv("PIN,DateTime\n1001,2026-09-12 08:00:00\n");

        $result = (new ImportAttendanceDeviceScans)->handle($path, $officeId);

        $this->assertSame(1, $result->imported);
        $this->assertSame(0, $result->skipped);
    }

    public function test_header_tanpa_kolom_pin_atau_waktu_ditolak(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');
        $path = $this->writeTempCsv("nomor,tanggal\n1001,2026-09-12 08:00:00\n");

        $this->expectException(RuntimeException::class);

        (new ImportAttendanceDeviceScans)->handle($path, $officeId);
    }

    /** Kalau pengguna sempat membuka berkas ekspor mesin di Excel dulu, baris "sep=,"/BOM UTF-8 tidak boleh menggagalkan pembacaan header. */
    public function test_berkas_dengan_baris_sep_dan_bom_excel_tetap_terbaca(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');
        $path = $this->writeTempCsv("\xEF\xBB\xBF"."sep=,\r\npin,waktu\r\n1001,2026-09-12 08:00:00\r\n");

        $result = (new ImportAttendanceDeviceScans)->handle($path, $officeId);

        $this->assertSame(1, $result->imported);
        $this->assertSame(0, $result->skipped);
    }

    public function test_kantor_tidak_ditemukan_ditolak(): void
    {
        $path = $this->writeTempCsv("pin,waktu\n1001,2026-09-12 08:00:00\n");

        $this->expectException(RuntimeException::class);

        (new ImportAttendanceDeviceScans)->handle($path, '00000000-0000-0000-0000-000000000000');
    }

    private function writeTempCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fp_csv_');
        file_put_contents($path, $content);

        return $path;
    }
}
