<?php

declare(strict_types=1);

namespace Tests\Feature\Shift;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tukar Shift — 1 TAHAP, Atasan Langsung SAJA (office-tree). Sengaja
 * TIDAK 2 tahap seperti Cuti/Lembur/SPPD — lihat ShiftSwapApprovalController.
 */
final class ShiftSwapApprovalQueueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_atasan_langsung_dapat_menyetujui_tukar_shift_bawahannya(): void
    {
        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, atasan_langsung KC Mataram
            ->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertRedirect(route('admin.shift-swap-queue'));
        $this->assertSame('approved', DB::table('shf_swap_requests')->where('id', $requestId)->value('status'));
    }

    public function test_atasan_kantor_lain_tidak_dapat_melihat_atau_memutus(): void
    {
        // Pemohon di KC Mataram, aktor Nur Aisyah HANYA pimpinan_kantor
        // KP (bukan atasan_langsung kantor mana pun) setelah dicabut.
        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));
        $dewi = $this->userWithNrp('2019.09.0177'); // KC Selong — bukan pohon kantor Mataram, bukan atasan_langsung
        $this->grantRole($dewi, 'atasan_langsung');

        $response = $this->actingAs($dewi)->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('shf_swap_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pemohon_tidak_dapat_menyetujui_pengajuannya_sendiri(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');
        $requestId = $this->insertSwapRequest($sitiId, $this->employeeId('2017.11.0119'));
        $this->grantRole($this->userWithNrp('2018.03.0142'), 'atasan_langsung');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertForbidden();
    }

    public function test_rekan_yang_dituju_tidak_dapat_menyetujui_meski_atasan_langsung(): void
    {
        $hendraId = $this->employeeId('2017.11.0119');
        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $hendraId);
        $this->grantRole($this->userWithNrp('2017.11.0119'), 'atasan_langsung');

        $response = $this->actingAs($this->userWithNrp('2017.11.0119'))
            ->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('shf_swap_requests')->where('id', $requestId)->value('status'));
    }

    public function test_peran_lain_ditolak_dari_antrean(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/persetujuan/tukar-shift');

        $response->assertForbidden();
    }

    private function insertSwapRequest(string $requestingEmployeeId, string $counterpartEmployeeId): string
    {
        $patternId = (string) Uuid7::generate();
        DB::table('shf_shift_patterns')->insert([
            'id' => $patternId,
            'code' => 'PAGI-'.uniqid(),
            'name' => 'Shift Pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'crosses_midnight' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $id = (string) Uuid7::generate();

        DB::table('shf_swap_requests')->insert([
            'id' => $id,
            'request_number' => 'TS/TEST/'.uniqid(),
            'requesting_employee_id' => $requestingEmployeeId,
            'counterpart_employee_id' => $counterpartEmployeeId,
            'swap_date' => now()->addDays(3)->format('Y-m-d'),
            'requesting_original_pattern_id' => $patternId,
            'counterpart_original_pattern_id' => $patternId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function grantRole(User $user, string $roleName): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        $alreadyHas = DB::table('model_has_roles')->where('model_id', $user->id)->where('role_id', $roleId)->exists();

        if (! $alreadyHas) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        }
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
