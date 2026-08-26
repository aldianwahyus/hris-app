<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SEC-2026-08: hr_admin (admin cabang) TIDAK PERNAH boleh mengajukan/
 * men-generate payroll — satu-satunya jalur generate ada di
 * PayrollBulkGenerationTest (hr_approver/Human Capital, BANK_WIDE).
 *
 * Wewenang BARU yang sempit (input potongan pada draf yang SUDAH dibuat
 * HC) TIDAK melanggar itu — lihat PayrollDeductionControllerTest untuk
 * cakupannya (kantor sendiri SAJA, hanya selama status draft).
 */
final class PayrollDraftScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_tidak_bisa_generate_payroll_lewat_rute_apa_pun(): void
    {
        // Tidak ada lagi rute POST /pegawai/payroll (generate per-kantor
        // oleh hr_admin) sejak SEC-2026-08 — path dasar ini sekarang
        // HANYA terdaftar untuk GET (indeks Potongan Gaji), jadi POST ke
        // situ 405 Method Not Allowed (path ada, verb tidak terdaftar),
        // BUKAN 404. Intinya sama: tidak ada jalur men-generate payroll.
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->post('/pegawai/payroll', ['period' => '2026-09']);

        $response->assertStatus(405);

        $officeId = DB::table('emp_employees')->where('nrp', '2021.05.0302')->value('office_id');
        $this->assertSame(
            0,
            DB::table('pay_payroll_runs')->where('office_id', $officeId)->where('period', '2026-09-01')->count(),
            'Admin cabang tidak boleh berhasil membuat draf payroll lewat jalur apa pun.'
        );
    }

    public function test_hr_admin_melihat_daftar_kosong_saat_belum_ada_draf_kantornya(): void
    {
        // GET /pegawai/payroll SEKARANG legitimately 200 (indeks Potongan
        // Gaji, PayrollDeductionController::index) — BUKAN lagi 404 total
        // seperti sebelum fitur potongan ada. Halaman ini hanya menampilkan
        // draf kantor SENDIRI; tidak menampilkan kontrol generate apa pun.
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/pegawai/payroll');

        $response->assertOk();
        $response->assertDontSeeText('Generate Semua Kantor');
        $response->assertDontSeeText('generate-massal');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
