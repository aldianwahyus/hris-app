<?php

declare(strict_types=1);

namespace Tests\Feature\Shift;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ShiftPatternTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sysadmin_dapat_menambah_pola_shift(): void
    {
        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->post('/admin/sistem/pola-shift', [
            'code' => 'PAGI',
            'name' => 'Shift Pagi',
            'start_time' => '07:00',
            'end_time' => '15:00',
        ]);

        $response->assertRedirect(route('sysadmin.shift-patterns.index'));
        $response->assertSessionHas('sukses');

        $pattern = DB::table('shf_shift_patterns')->where('code', 'PAGI')->first();
        $this->assertNotNull($pattern);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'shift_pattern')->where('auditable_id', $pattern->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($audit);
    }

    public function test_admin_hc_dapat_menambah_pola_shift(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post('/admin/sistem/pola-shift', [
            'code' => 'MALAM',
            'name' => 'Shift Malam',
            'start_time' => '23:00',
            'end_time' => '07:00',
            'crosses_midnight' => '1',
        ]);

        $response->assertRedirect(route('sysadmin.shift-patterns.index'));
        $this->assertSame(1, DB::table('shf_shift_patterns')->where('code', 'MALAM')->where('crosses_midnight', true)->count());
    }

    public function test_kode_duplikat_ditolak(): void
    {
        DB::table('shf_shift_patterns')->insert([
            'id' => (string) Uuid7::generate(), 'code' => 'PAGI', 'name' => 'Shift Pagi',
            'start_time' => '07:00:00', 'end_time' => '15:00:00', 'crosses_midnight' => false,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->post('/admin/sistem/pola-shift', [
            'code' => 'PAGI', 'name' => 'Duplikat', 'start_time' => '08:00', 'end_time' => '16:00',
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('shf_shift_patterns')->where('code', 'PAGI')->count());
    }

    public function test_pola_masih_dipakai_tidak_dapat_dihapus(): void
    {
        $patternId = (string) Uuid7::generate();
        DB::table('shf_shift_patterns')->insert([
            'id' => $patternId, 'code' => 'PAGI', 'name' => 'Shift Pagi',
            'start_time' => '07:00:00', 'end_time' => '15:00:00', 'crosses_midnight' => false,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);
        DB::table('shf_employee_assignments')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => DB::table('emp_employees')->where('nrp', '2015.07.0088')->value('id'),
            'shift_pattern_id' => $patternId,
            'effective_from' => '2020-01-01', 'effective_to' => null,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->delete("/admin/sistem/pola-shift/{$patternId}");

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('shf_shift_patterns')->where('id', $patternId)->whereNotNull('deleted_at')->count());
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/admin/sistem/pola-shift');

        $response->assertForbidden();
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
