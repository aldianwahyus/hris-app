<?php

declare(strict_types=1);

namespace Tests\Feature\Overtime;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsOvertimeAttendance;
use Tests\TestCase;

/**
 * Lapisan HTTP dari pengajuan lembur (DEC-37): form TIDAK memiliki
 * kolom jam yang dapat diisi manual, dan pratinjau GET menampilkan
 * jam yang benar-benar dibaca dari absensi.
 */
final class OvertimeAttendanceEvidenceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_formulir_tidak_memiliki_input_jam_yang_dapat_diisi_manual(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/lembur/ajukan');

        $response->assertOk();
        $response->assertDontSee('name="planned_hours"', false);
    }

    public function test_pratinjau_get_menampilkan_jam_dari_absensi(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $this->seedOvertimeAttendance($employeeId, new DateTimeImmutable('2026-09-02'), 3.0);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->get('/lembur/ajukan?work_date=2026-09-02');

        $response->assertOk();
        $response->assertSeeText('Bukti Lembur dari Absensi');
        $response->assertSeeText('3 jam');
    }

    public function test_pratinjau_get_tanpa_bukti_absensi_menampilkan_notifikasi(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->get('/lembur/ajukan?work_date=2026-09-02');

        $response->assertOk();
        $response->assertSeeText('tidak ada lembur');
    }

    public function test_pengajuan_tanpa_bukti_absensi_ditolak_via_http(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->post('/lembur/ajukan', [
            'overtime_type' => 'regular',
            'work_date' => '2026-09-02',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('tidak ada lembur', session('gagal'));

        $employeeId = $this->employeeId('2018.03.0142');
        $this->assertSame(
            0,
            DB::table('ovt_requests')->where('employee_id', $employeeId)->where('work_date', '2026-09-02')->count()
        );
    }

    public function test_pengajuan_dengan_bukti_absensi_berhasil_via_http(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $this->seedOvertimeAttendance($employeeId, new DateTimeImmutable('2026-09-02'), 3.0);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->post('/lembur/ajukan', [
            'overtime_type' => 'regular',
            'work_date' => '2026-09-02',
        ]);

        $response->assertRedirect(route('ess.dashboard'));
        $response->assertSessionHas('sukses');

        $row = DB::table('ovt_requests')->where('employee_id', $employeeId)->first();
        $this->assertEquals(3.0, (float) $row->planned_hours);
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
