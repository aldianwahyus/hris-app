<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Console\Commands\CheckExpiringContracts;
use App\Models\User;
use App\Notifications\ContractExpiringSoon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Manajemen Kontrak (pegawai kontrak/outsource) — modul baru (evaluasi
 * PM/client 2026-09-02), pola PERSIS EmployeeFamilyMemberController
 * (HR-only, tulis langsung, lingkup ResolveEmployeeForHrAction).
 */
final class EmployeeContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_dapat_menambah_kontrak_pegawai_kantornya(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina Marlina, hr_admin KCP Gerung
        $employeeId = $this->employeeIdInSameOfficeAs('2021.05.0302');

        $response = $this->actingAs($hrAdmin)->post("/pegawai/{$employeeId}/kontrak", [
            'contract_number' => 'KTR-UJI-0001',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'contract_type' => 'kontrak',
        ]);

        $response->assertRedirect();
        $contract = DB::table('emp_contracts')->where('contract_number', 'KTR-UJI-0001')->first();
        $this->assertNotNull($contract);
        $this->assertSame('aktif', $contract->status);
    }

    public function test_hr_admin_tidak_bisa_menambah_kontrak_pegawai_kantor_lain(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // KCP Gerung
        $employeeId = $this->employeeId('2018.03.0142'); // Siti, KC Mataram — kantor lain

        $response = $this->actingAs($hrAdmin)->post("/pegawai/{$employeeId}/kontrak", [
            'contract_number' => 'KTR-UJI-0002',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'contract_type' => 'kontrak',
        ]);

        $response->assertForbidden();
    }

    public function test_memperpanjang_kontrak_membuat_baris_baru_dan_menandai_lama_diperpanjang(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $employeeId = $this->employeeId('2018.03.0142');

        $this->actingAs($sysAdmin)->post("/pegawai/{$employeeId}/kontrak", [
            'contract_number' => 'KTR-UJI-0003', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'contract_type' => 'kontrak',
        ]);
        $oldId = DB::table('emp_contracts')->where('contract_number', 'KTR-UJI-0003')->value('id');

        $response = $this->actingAs($sysAdmin)->post("/pegawai/{$employeeId}/kontrak/{$oldId}/perpanjang", [
            'contract_number' => 'KTR-UJI-0003-B', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);

        $response->assertRedirect();
        $this->assertSame('diperpanjang', DB::table('emp_contracts')->where('id', $oldId)->value('status'));

        $new = DB::table('emp_contracts')->where('contract_number', 'KTR-UJI-0003-B')->first();
        $this->assertNotNull($new);
        $this->assertSame('aktif', $new->status);
        $this->assertSame($oldId, $new->renewed_from_contract_id);
        $this->assertSame('kontrak', $new->contract_type, 'Jenis kontrak diwarisi dari kontrak lama.');
    }

    public function test_menandai_kontrak_berakhir(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $employeeId = $this->employeeId('2018.03.0142');
        $this->actingAs($sysAdmin)->post("/pegawai/{$employeeId}/kontrak", [
            'contract_number' => 'KTR-UJI-0004', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'contract_type' => 'outsource',
        ]);
        $id = DB::table('emp_contracts')->where('contract_number', 'KTR-UJI-0004')->value('id');

        $response = $this->actingAs($sysAdmin)->post("/pegawai/{$employeeId}/kontrak/{$id}/status", ['status' => 'berakhir']);

        $response->assertRedirect();
        $this->assertSame('berakhir', DB::table('emp_contracts')->where('id', $id)->value('status'));
    }

    public function test_command_mengirim_pengingat_kontrak_yang_akan_berakhir_dan_tidak_mengirim_ganda(): void
    {
        Notification::fake();

        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $employeeId = $this->employeeId('2021.05.0302'); // Rina, KCP Gerung

        $endDate = now()->addDays(10)->format('Y-m-d');
        $this->actingAs($sysAdmin)->post("/pegawai/{$employeeId}/kontrak", [
            'contract_number' => 'KTR-UJI-0005', 'start_date' => '2026-01-01', 'end_date' => $endDate, 'contract_type' => 'kontrak',
        ]);

        $this->artisan(CheckExpiringContracts::class)->assertExitCode(0);

        Notification::assertSentTo(
            $this->userWithNrp('2014.02.0061'), // hr_approver
            ContractExpiringSoon::class,
        );

        $sentAt = DB::table('emp_contracts')->where('contract_number', 'KTR-UJI-0005')->value('reminder_sent_at');
        $this->assertNotNull($sentAt);

        // Jalankan lagi — TIDAK boleh mengirim ganda (reminder_sent_at sudah terisi).
        $this->artisan(CheckExpiringContracts::class);
        Notification::assertSentToTimes($this->userWithNrp('2014.02.0061'), ContractExpiringSoon::class, 1);
    }

    public function test_command_tidak_mengirim_untuk_kontrak_di_luar_ambang_waktu(): void
    {
        Notification::fake();

        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $employeeId = $this->employeeId('2021.05.0302');

        $farEndDate = now()->addDays(90)->format('Y-m-d'); // di luar ambang 30 hari default
        $this->actingAs($sysAdmin)->post("/pegawai/{$employeeId}/kontrak", [
            'contract_number' => 'KTR-UJI-0006', 'start_date' => '2026-01-01', 'end_date' => $farEndDate, 'contract_type' => 'kontrak',
        ]);

        $this->artisan(CheckExpiringContracts::class);

        Notification::assertNothingSent();
        $this->assertNull(DB::table('emp_contracts')->where('contract_number', 'KTR-UJI-0006')->value('reminder_sent_at'));
    }

    private function employeeIdInSameOfficeAs(string $nrp): string
    {
        $officeId = DB::table('emp_employees')->where('nrp', $nrp)->value('office_id');

        return DB::table('emp_employees')->where('office_id', $officeId)->where('nrp', '!=', $nrp)->value('id')
            ?? DB::table('emp_employees')->where('nrp', $nrp)->value('id');
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
