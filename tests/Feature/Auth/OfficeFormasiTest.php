<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OfficeFormasiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sysadmin_dapat_menetapkan_formasi_kantor(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))
            ->post("/admin/sistem/formasi-kantor/{$officeId}", ['authorized_headcount' => 25]);

        $response->assertRedirect(route('sysadmin.office-formasi.index'));
        $response->assertSessionHas('sukses');
        $this->assertSame(25, DB::table('md_offices')->where('id', $officeId)->value('authorized_headcount'));

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'md_office')->where('auditable_id', $officeId)
            ->where('action', 'updated')->first();
        $this->assertNotNull($audit);
    }

    public function test_admin_hc_dapat_menetapkan_formasi_kantor(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KCP-GRG')->value('id');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->post("/admin/sistem/formasi-kantor/{$officeId}", ['authorized_headcount' => 10]);

        $response->assertRedirect(route('sysadmin.office-formasi.index'));
        $this->assertSame(10, DB::table('md_offices')->where('id', $officeId)->value('authorized_headcount'));
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/admin/sistem/formasi-kantor');

        $response->assertForbidden();
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
