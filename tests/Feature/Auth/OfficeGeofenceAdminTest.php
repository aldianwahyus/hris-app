<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Titik ordinat kantor — Admin Sistem, tulis langsung (bukan maker-checker). */
final class OfficeGeofenceAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_mengubah_titik_ordinat_kantor(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/kantor-geofence/{$officeId}", [
            'latitude' => -8.5833333,
            'longitude' => 116.1166667,
            'geofence_radius_m' => 150,
        ]);

        $response->assertRedirect(route('sysadmin.office-geofence.index'));
        $response->assertSessionHas('sukses');

        $office = DB::table('md_offices')->where('id', $officeId)->first();
        $this->assertEqualsWithDelta(-8.5833333, (float) $office->latitude, 0.0000001);
        $this->assertEqualsWithDelta(116.1166667, (float) $office->longitude, 0.0000001);
        $this->assertSame(150, $office->geofence_radius_m);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'md_office')->where('auditable_id', $officeId)->where('action', 'updated')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_latitude_di_luar_rentang_ditolak(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');

        $response = $this->actingAs($this->sysAdmin())->post("/admin/sistem/kantor-geofence/{$officeId}", [
            'latitude' => 200,
            'longitude' => 116.1,
            'geofence_radius_m' => 100,
        ]);

        $response->assertSessionHasErrors('latitude');
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/kantor-geofence');

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
