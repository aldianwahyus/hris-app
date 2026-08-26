<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Skala Imbalan Kerja — Admin Sistem, nilai lama ditutup bukan ditimpa. */
final class SalaryScaleAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_menambah_nilai_baru_menutup_nilai_lama(): void
    {
        $lamaId = DB::table('pay_salary_scale')->where('person_grade', 8)->where('step', 1)->whereNull('effective_to')->value('id');

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/skala-gaji', [
            'person_grade' => 8,
            'step' => 1,
            'amount' => 2200000,
            'effective_from' => '2027-01-01',
            'source_document' => 'KEP/UJI/2027',
        ]);

        $response->assertRedirect(route('sysadmin.salary-scale.index'));
        $response->assertSessionHas('sukses');

        $this->assertSame('2026-12-31', DB::table('pay_salary_scale')->where('id', $lamaId)->value('effective_to'));

        $baru = DB::table('pay_salary_scale')->where('person_grade', 8)->where('step', 1)->whereNull('effective_to')->first();
        $this->assertSame(220_000_000, $baru->amount_cents);

        $audit = DB::table('aud_change_logs')->where('auditable_type', 'pay_salary_scale')->where('action', 'parameter_value_added')->first();
        $this->assertNotNull($audit);
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/skala-gaji');

        $response->assertForbidden();
    }

    private function sysAdmin(): User
    {
        return $this->userWithNrp('SYSADMIN');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
