<?php

declare(strict_types=1);

namespace Tests\Feature\Shift;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ShiftAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_penugasan_baru_menutup_penugasan_lama_yang_masih_terbuka(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', '2015.07.0088')->value('id');
        $pagiId = $this->seedPattern('PAGI');
        $siangId = $this->seedPattern('SIANG');

        $oldAssignmentId = (string) Uuid7::generate();
        DB::table('shf_employee_assignments')->insert([
            'id' => $oldAssignmentId, 'employee_id' => $employeeId, 'shift_pattern_id' => $pagiId,
            'effective_from' => '2026-01-01', 'effective_to' => null,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->post('/admin/sistem/penugasan-shift', [
            'employee_id' => $employeeId,
            'shift_pattern_id' => $siangId,
            'effective_from' => '2026-06-01',
        ]);

        $response->assertRedirect(route('sysadmin.shift-assignments.index'));

        $old = DB::table('shf_employee_assignments')->where('id', $oldAssignmentId)->first();
        $this->assertSame('2026-05-31', $old->effective_to);

        $new = DB::table('shf_employee_assignments')
            ->where('employee_id', $employeeId)->where('shift_pattern_id', $siangId)->first();
        $this->assertNotNull($new);
        $this->assertNull($new->effective_to);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'shift_assignment')->where('auditable_id', $new->id)->first();
        $this->assertNotNull($audit);
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/admin/sistem/penugasan-shift');

        $response->assertForbidden();
    }

    private function seedPattern(string $code): string
    {
        $id = (string) Uuid7::generate();

        DB::table('shf_shift_patterns')->insert([
            'id' => $id, 'code' => $code.'-'.uniqid(), 'name' => 'Shift '.$code,
            'start_time' => '07:00:00', 'end_time' => '15:00:00', 'crosses_midnight' => false,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
