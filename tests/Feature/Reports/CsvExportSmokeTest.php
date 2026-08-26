<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Setiap halaman "laporan"/"rekap" sekarang punya tombol Ekspor CSV
 * (dipakai App\Interfaces\Http\Support\CsvExport, satu pola dipakai
 * bersama). Tes ini MEMASTIKAN semua rute ekspor benar-benar
 * menghasilkan berkas CSV (200 + Content-Type text/csv), bukan 500 —
 * tidak menguji ISI kolom secara rinci (itu sudah cukup diwakili
 * query yang sama dipakai tampilan halaman masing-masing, sudah diuji
 * di tes fitur masing-masing modul).
 */
final class CsvExportSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ekspor_rekap_biaya_lembur(): void
    {
        $this->assertCsvDownload('/pegawai/lembur-biaya/ekspor', $this->userWithNrp('2014.02.0061')); // hr_approver
    }

    public function test_ekspor_rekap_absensi_harian_dan_bulanan(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin
        $this->assertCsvDownload('/pegawai/absensi/ekspor', $rina);
        $this->assertCsvDownload('/pegawai/absensi/ekspor?tampilan=bulanan', $rina);
    }

    public function test_ekspor_log_audit(): void
    {
        $this->assertCsvDownload('/log-audit/ekspor', $this->userWithNrp('2020.01.0231')); // auditor
    }

    public function test_ekspor_laporan_lms(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');

        foreach ([
            '/admin/pelatihan/analitik/ekspor',
            '/admin/pelatihan/analitik/pelatihan/ekspor',
            '/admin/pelatihan/analitik/evaluasi/ekspor',
            '/admin/pelatihan/analitik/kompetensi/ekspor',
            '/admin/pelatihan/analitik/talenta/ekspor',
            '/admin/pelatihan/evaluasi-pre-post/ekspor',
        ] as $url) {
            $this->assertCsvDownload($url, $hrApprover);
        }
    }

    private function assertCsvDownload(string $url, User $actor): void
    {
        $response = $this->actingAs($actor)->get($url);

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
