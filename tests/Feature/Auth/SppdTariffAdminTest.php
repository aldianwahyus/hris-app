<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Tarif SPPD — Admin Sistem, nilai lama ditutup bukan ditimpa. */
final class SppdTariffAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_menambah_tarif_baru_menutup_yang_lama(): void
    {
        $lamaId = DB::table('pay_sppd_tariffs')
            ->where('component', 'uang_makan')
            ->where('trip_category', 'jarak_jauh_keluar_provinsi')
            ->where('jabatan_tier', 'non_staff')
            ->whereNull('effective_to')
            ->value('id');
        $this->assertNotNull($lamaId);

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/tarif-sppd', [
            'component' => 'uang_makan',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'jabatan_tier' => 'non_staff',
            'currency' => 'IDR',
            'amount' => 220000,
            'effective_from' => '2027-01-01',
            'source_document' => 'BPP/UJI/2027',
        ]);

        $response->assertRedirect(route('sysadmin.sppd-tariffs.index'));
        $response->assertSessionHas('sukses');

        $this->assertSame('2026-12-31', DB::table('pay_sppd_tariffs')->where('id', $lamaId)->value('effective_to'));

        $baru = DB::table('pay_sppd_tariffs')
            ->where('component', 'uang_makan')->where('trip_category', 'jarak_jauh_keluar_provinsi')
            ->where('jabatan_tier', 'non_staff')->whereNull('effective_to')->first();
        $this->assertSame(22_000_000, $baru->amount_cents);
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/tarif-sppd');

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
