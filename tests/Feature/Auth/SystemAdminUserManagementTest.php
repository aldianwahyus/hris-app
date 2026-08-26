<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin Sistem (IT) — lingkup TEKNIS (akun login), BUKAN proses bisnis
 * SDM. Sengaja bukan "Super Admin": tidak pernah menyentuh data cuti/
 * lembur/pegawai, hanya mengelola kredensial (Role::SystemAdmin).
 */
final class SystemAdminUserManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_dapat_melihat_daftar_pengguna(): void
    {
        $response = $this->actingAs($this->sysAdmin())->get('/admin/sistem/pengguna');

        $response->assertOk();
        $response->assertSeeText('Ahmad Fauzi');
        $response->assertSeeText('Administrator Sistem');
    }

    public function test_peran_lain_ditolak_dari_manajemen_pengguna(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/sistem/pengguna');

        $response->assertForbidden();
    }

    public function test_reset_kata_sandi_mengubah_hash_dan_mencatat_audit(): void
    {
        $target = $this->userWithNrp('2018.03.0142');
        $hashSebelum = $target->password;

        $response = $this->actingAs($this->sysAdmin())
            ->post("/admin/sistem/pengguna/{$target->id}/reset-kata-sandi");

        $response->assertRedirect(route('sysadmin.users.index'));
        $response->assertSessionHas('kata_sandi_baru');

        $hashSesudah = DB::table('users')->where('id', $target->id)->value('password');
        $this->assertNotSame($hashSebelum, $hashSesudah);
        $this->assertTrue(Hash::check(session('kata_sandi_baru'), $hashSesudah));

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'user_account')
            ->where('auditable_id', $target->employee_id)
            ->where('action', 'password_reset')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_peran_lain_ditolak_mereset_kata_sandi(): void
    {
        $target = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/admin/sistem/pengguna/{$target->id}/reset-kata-sandi");

        $response->assertForbidden();
    }

    public function test_system_admin_dapat_memberi_dan_mencabut_peran_pengguna_lain(): void
    {
        $target = $this->userWithNrp('2018.03.0142'); // Siti — hanya "pegawai"

        $response = $this->actingAs($this->sysAdmin())
            ->post("/admin/sistem/pengguna/{$target->id}/peran", ['role' => 'auditor']);

        $response->assertRedirect(route('sysadmin.users.index'));
        $this->assertTrue($target->fresh()->hasRole('auditor'));

        $grantAudit = DB::table('aud_change_logs')
            ->where('auditable_type', 'user_account')->where('auditable_id', $target->employee_id)
            ->where('action', 'role_granted')->first();
        $this->assertNotNull($grantAudit);
        $this->assertStringContainsString('auditor', $grantAudit->new_values);

        $response = $this->actingAs($this->sysAdmin())
            ->post("/admin/sistem/pengguna/{$target->id}/peran/auditor/cabut");

        $response->assertRedirect(route('sysadmin.users.index'));
        $this->assertFalse($target->fresh()->hasRole('auditor'));

        $revokeAudit = DB::table('aud_change_logs')
            ->where('auditable_type', 'user_account')->where('auditable_id', $target->employee_id)
            ->where('action', 'role_revoked')->first();
        $this->assertNotNull($revokeAudit);
    }

    public function test_pemberian_peran_ganda_ditolak_dengan_pesan_bukan_duplikasi(): void
    {
        $target = $this->userWithNrp('2015.07.0088'); // Ahmad — sudah atasan_langsung

        $response = $this->actingAs($this->sysAdmin())
            ->post("/admin/sistem/pengguna/{$target->id}/peran", ['role' => 'atasan_langsung']);

        $response->assertSessionHas('gagal');
        $this->assertSame(
            1,
            DB::table('model_has_roles')
                ->where('model_id', $target->id)
                ->whereIn('role_id', DB::table('roles')->where('name', 'atasan_langsung')->pluck('id'))
                ->count()
        );
    }

    /**
     * SEC-2026-08: sebelum diperbaiki, assignRole() memanggil
     * $user->assignRole() langsung tanpa memeriksa
     * SegregationOfDutyPolicy — satu akun bisa diberi hr_admin (maker)
     * DAN hr_approver (checker) sekaligus, melumpuhkan maker-checker.
     */
    public function test_hr_admin_tidak_dapat_diberi_hr_approver_sekaligus(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // sudah hr_admin (seed)
        $this->assertTrue($rina->hasRole('hr_admin'));

        $response = $this->actingAs($this->sysAdmin())
            ->post("/admin/sistem/pengguna/{$rina->id}/peran", ['role' => 'hr_approver']);

        $response->assertSessionHas('gagal');
        $this->assertFalse($rina->fresh()->hasRole('hr_approver'), 'hr_admin dan hr_approver saling eksklusif (§6.3).');
    }

    public function test_auditor_tidak_dapat_diberi_peran_operasional(): void
    {
        $target = $this->userWithNrp('2018.03.0142'); // "pegawai" biasa

        $this->actingAs($this->sysAdmin())
            ->post("/admin/sistem/pengguna/{$target->id}/peran", ['role' => 'auditor']);
        $this->assertTrue($target->fresh()->hasRole('auditor'));

        $response = $this->actingAs($this->sysAdmin())
            ->post("/admin/sistem/pengguna/{$target->id}/peran", ['role' => 'hr_admin']);

        $response->assertSessionHas('gagal');
        $this->assertFalse($target->fresh()->hasRole('hr_admin'), 'Auditor harus independen dari peran operasional (§6.3).');
    }

    public function test_system_admin_tidak_dapat_mengubah_peran_akun_sendiri(): void
    {
        $sysAdmin = $this->sysAdmin();

        $responseBeri = $this->actingAs($sysAdmin)
            ->post("/admin/sistem/pengguna/{$sysAdmin->id}/peran", ['role' => 'auditor']);
        $responseBeri->assertForbidden();

        $responseCabut = $this->actingAs($sysAdmin)
            ->post("/admin/sistem/pengguna/{$sysAdmin->id}/peran/system_admin/cabut");
        $responseCabut->assertForbidden();

        $this->assertTrue($sysAdmin->fresh()->hasRole('system_admin'));
    }

    public function test_peran_tidak_valid_ditolak_validasi(): void
    {
        $target = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($this->sysAdmin())
            ->post("/admin/sistem/pengguna/{$target->id}/peran", ['role' => 'bukan_peran_asli']);

        $response->assertSessionHasErrors('role');
    }

    public function test_peran_lain_ditolak_mengubah_penugasan_peran(): void
    {
        $target = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/admin/sistem/pengguna/{$target->id}/peran", ['role' => 'auditor']);

        $response->assertForbidden();
    }

    public function test_admin_hc_dapat_melihat_dan_mengelola_peran_pengguna(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061'); // Nur Aisyah — hr_approver
        $target = $this->userWithNrp('2018.03.0142'); // Siti — hanya "pegawai"

        $responseIndex = $this->actingAs($hrApprover)->get('/admin/sistem/pengguna');
        $responseIndex->assertOk();

        $responseGrant = $this->actingAs($hrApprover)
            ->post("/admin/sistem/pengguna/{$target->id}/peran", ['role' => 'auditor']);
        $responseGrant->assertRedirect(route('sysadmin.users.index'));
        $this->assertTrue($target->fresh()->hasRole('auditor'));

        $responseRevoke = $this->actingAs($hrApprover)
            ->post("/admin/sistem/pengguna/{$target->id}/peran/auditor/cabut");
        $responseRevoke->assertRedirect(route('sysadmin.users.index'));
        $this->assertFalse($target->fresh()->hasRole('auditor'));
    }

    public function test_admin_hc_tidak_dapat_mereset_kata_sandi(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $target = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($hrApprover)
            ->post("/admin/sistem/pengguna/{$target->id}/reset-kata-sandi");

        $response->assertForbidden();
    }

    public function test_admin_hc_tetap_tunduk_pada_pemisahan_tugas_saat_memberi_peran(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $rina = $this->userWithNrp('2021.05.0302'); // sudah hr_admin (seed)

        $response = $this->actingAs($hrApprover)
            ->post("/admin/sistem/pengguna/{$rina->id}/peran", ['role' => 'hr_approver']);

        $response->assertSessionHas('gagal');
        $this->assertFalse($rina->fresh()->hasRole('hr_approver'), 'hr_admin dan hr_approver saling eksklusif (§6.3) — berlaku terlepas siapa aktornya.');
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
