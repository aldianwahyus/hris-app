<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Data pegawai untuk Admin SDM — lingkup OFFICE (ARCH-001 §6.2), bukan
 * OFFICE_TREE: hanya kantor yang ditugaskan, tanpa kantor turunannya.
 */
final class EmployeeDirectoryScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_hanya_melihat_pegawai_kantornya_sendiri(): void
    {
        // Rina Marlina — Adm, KCP Gerung.
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/pegawai');

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');    // KCP Gerung — kantornya sendiri
        $response->assertDontSeeText('Dewi Lestari'); // KC Selong — induk KCP Gerung, TIDAK termasuk (OFFICE, bukan OFFICE_TREE)
        $response->assertDontSeeText('Ahmad Fauzi');  // KC Mataram — kantor lain
    }

    public function test_pegawai_biasa_ditolak_dari_data_pegawai(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/pegawai');

        $response->assertForbidden();
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
