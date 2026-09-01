<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kontrol menu Aplikasi Mobile — SYSTEM_ADMIN/hr_approver (permission
 * sysadmin-content.manage, SAMA dengan Daftar Kantor/Kalender Libur/dst.).
 * Satu saklar per menu berlaku BANK-WIDE, dibaca aplikasi mobile lewat
 * Api\V1\MobileMenuApiController — lihat MobileMenuApiTest untuk sisi itu.
 */
final class MobileMenuSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_melihat_dan_mematikan_menu(): void
    {
        $index = $this->actingAs($this->sysAdmin())->get('/admin/sistem/menu-mobile');
        $index->assertOk();
        $index->assertSeeText('SPPD');

        $enabledKeys = DB::table('mobile_menu_items')->where('key', '!=', 'sppd')->pluck('key')->all();

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/menu-mobile', [
            'enabled_keys' => $enabledKeys,
        ]);

        $response->assertRedirect(route('sysadmin.mobile-menu.index'));
        $response->assertSessionHas('sukses');

        $this->assertFalse((bool) DB::table('mobile_menu_items')->where('key', 'sppd')->value('is_enabled'));
        $this->assertTrue((bool) DB::table('mobile_menu_items')->where('key', 'cuti')->value('is_enabled'));

        $sppdItemId = DB::table('mobile_menu_items')->where('key', 'sppd')->value('id');
        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'mobile_menu_item')
            ->where('auditable_id', $sppdItemId)
            ->where('action', 'updated')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_hr_approver_dapat_mengubah_pengaturan(): void
    {
        $allKeys = DB::table('mobile_menu_items')->pluck('key')->all();

        $response = $this->actingAs($this->userWithNrp('2014.02.0061')) // hr_approver
            ->post('/admin/sistem/menu-mobile', ['enabled_keys' => $allKeys]);

        $response->assertRedirect(route('sysadmin.mobile-menu.index'));
    }

    public function test_menyimpan_tanpa_perubahan_tidak_menulis_audit_baru(): void
    {
        $allKeys = DB::table('mobile_menu_items')->pluck('key')->all();
        $auditCountBefore = DB::table('aud_change_logs')->where('auditable_type', 'mobile_menu_item')->count();

        $response = $this->actingAs($this->sysAdmin())->post('/admin/sistem/menu-mobile', ['enabled_keys' => $allKeys]);

        $response->assertSessionHas('sukses');
        $this->assertSame($auditCountBefore, DB::table('aud_change_logs')->where('auditable_type', 'mobile_menu_item')->count());
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/menu-mobile');

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
