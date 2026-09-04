<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Report Builder (Fase 2) — registry subjek (ReportSubjectRegistry),
 * lingkup SAMA income-recap.view (hr_admin: kantornya sendiri,
 * hr_approver: seluruh bank).
 */
final class ReportBuilderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_approver_dapat_melihat_daftar_subjek(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/laporan');

        $response->assertOk();
        $response->assertSeeText('Data Pegawai');
        $response->assertSeeText('Absensi');
        $response->assertSeeText('Cuti');
        $response->assertSeeText('Ringkasan Payroll');
        $response->assertSeeText('Aset');
    }

    public function test_hr_approver_dapat_melihat_form_kolom_subjek(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/laporan/pegawai');

        $response->assertOk();
        $response->assertSeeText('NRP');
        $response->assertSeeText('Nama Lengkap');
    }

    public function test_subjek_tidak_dikenal_menghasilkan_404(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/laporan/tidak-ada');

        $response->assertNotFound();
    }

    public function test_hr_approver_mengunduh_csv_bank_wide(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->get('/laporan/pegawai/unduh?'.http_build_query(['columns' => ['nrp', 'full_name'], 'format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Siti Rahmawati', $csv);
        $this->assertStringContainsString('Rina Marlina', $csv);
    }

    public function test_hr_admin_hanya_melihat_pegawai_kantornya_sendiri(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302')) // Rina Marlina — hr_admin KCP Gerung
            ->get('/laporan/pegawai/unduh?'.http_build_query(['columns' => ['nrp', 'full_name'], 'format' => 'csv']));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Rina Marlina', $csv);
        $this->assertStringNotContainsString('Siti Rahmawati', $csv);
    }

    public function test_pdf_diunduh_dengan_content_type_yang_benar(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->get('/laporan/pegawai/unduh?'.http_build_query(['columns' => ['nrp', 'full_name'], 'format' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_kolom_tidak_valid_ditolak_dengan_pesan_kegagalan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->get('/laporan/pegawai/unduh?'.http_build_query(['columns' => ['kolom_tidak_ada'], 'format' => 'csv']));

        $response->assertRedirect(route('hr.report-builder.show', 'pegawai'));
        $response->assertSessionHas('gagal');
    }

    public function test_filter_status_diterapkan(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->get('/laporan/pegawai/unduh?'.http_build_query([
                'columns' => ['nrp', 'full_name'],
                'status' => 'kontrak',
                'format' => 'csv',
            ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        // Siti & Rina fixture statusnya 'tetap' — tersaring habis oleh filter 'kontrak'.
        $this->assertStringNotContainsString('Siti Rahmawati', $csv);
    }

    public function test_pegawai_biasa_tidak_bisa_mengakses_report_builder(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/laporan');

        $response->assertForbidden();
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
