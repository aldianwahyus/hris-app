<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Manajemen Aset — modul baru (evaluasi PM/client 2026-09-02).
 * hr_admin: kantornya sendiri (OFFICE). hr_approver/system_admin:
 * seluruh bank (BANK_WIDE) — lihat AssetController::index().
 */
final class AssetManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_dapat_menambah_aset_kantornya(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina Marlina, hr_admin KCP Gerung
        $officeId = DB::table('emp_employees')->where('nrp', '2021.05.0302')->value('office_id');

        $response = $this->actingAs($hrAdmin)->post('/admin/sistem/aset', [
            'asset_code' => 'LT-UJI-0001',
            'name' => 'Laptop Uji',
            'category' => 'Laptop',
            'office_id' => $officeId,
        ]);

        $response->assertRedirect(route('sysadmin.assets.index'));
        $asset = DB::table('ast_assets')->where('asset_code', 'LT-UJI-0001')->first();
        $this->assertNotNull($asset);
        $this->assertSame('tersedia', $asset->status);
        $this->assertSame('baik', $asset->condition);
    }

    public function test_kode_aset_duplikat_ditolak(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $officeId = DB::table('md_offices')->value('id');

        $this->actingAs($sysAdmin)->post('/admin/sistem/aset', [
            'asset_code' => 'LT-UJI-0002', 'name' => 'Laptop A', 'category' => 'Laptop', 'office_id' => $officeId,
        ]);

        $response = $this->actingAs($sysAdmin)->post('/admin/sistem/aset', [
            'asset_code' => 'LT-UJI-0002', 'name' => 'Laptop B', 'category' => 'Laptop', 'office_id' => $officeId,
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('ast_assets')->where('asset_code', 'LT-UJI-0002')->count());
    }

    public function test_menugaskan_aset_mengubah_status_dipakai_dan_tercatat_di_riwayat(): void
    {
        $assetId = $this->createAsset();
        $employeeId = $this->employeeId('2018.03.0142'); // Siti

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->post("/admin/sistem/aset/{$assetId}/tugaskan", [
            'employee_id' => $employeeId,
        ]);

        $response->assertRedirect();
        $this->assertSame('dipakai', DB::table('ast_assets')->where('id', $assetId)->value('status'));

        $assignment = DB::table('ast_assignments')->where('asset_id', $assetId)->where('employee_id', $employeeId)->first();
        $this->assertNotNull($assignment);
        $this->assertNull($assignment->returned_at);
    }

    public function test_tidak_bisa_menugaskan_aset_yang_sudah_dipakai(): void
    {
        $assetId = $this->createAsset();
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $this->actingAs($sysAdmin)->post("/admin/sistem/aset/{$assetId}/tugaskan", [
            'employee_id' => $this->employeeId('2018.03.0142'),
        ]);

        $response = $this->actingAs($sysAdmin)->post("/admin/sistem/aset/{$assetId}/tugaskan", [
            'employee_id' => $this->employeeId('2017.11.0119'),
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('ast_assignments')->where('asset_id', $assetId)->count());
    }

    public function test_mengembalikan_aset_kondisi_baik_langsung_tersedia_lagi(): void
    {
        $assetId = $this->createAsset();
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $this->actingAs($sysAdmin)->post("/admin/sistem/aset/{$assetId}/tugaskan", ['employee_id' => $this->employeeId('2018.03.0142')]);
        $assignmentId = DB::table('ast_assignments')->where('asset_id', $assetId)->value('id');

        $response = $this->actingAs($sysAdmin)->post("/admin/sistem/penugasan-aset/{$assignmentId}/kembalikan", [
            'returned_condition' => 'baik',
        ]);

        $response->assertRedirect();
        $this->assertSame('tersedia', DB::table('ast_assets')->where('id', $assetId)->value('status'));
        $this->assertNotNull(DB::table('ast_assignments')->where('id', $assignmentId)->value('returned_at'));
    }

    public function test_mengembalikan_aset_kondisi_rusak_jadi_status_perbaikan(): void
    {
        $assetId = $this->createAsset();
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $this->actingAs($sysAdmin)->post("/admin/sistem/aset/{$assetId}/tugaskan", ['employee_id' => $this->employeeId('2018.03.0142')]);
        $assignmentId = DB::table('ast_assignments')->where('asset_id', $assetId)->value('id');

        $this->actingAs($sysAdmin)->post("/admin/sistem/penugasan-aset/{$assignmentId}/kembalikan", [
            'returned_condition' => 'rusak_berat',
        ]);

        $this->assertSame('perbaikan', DB::table('ast_assets')->where('id', $assetId)->value('status'));
    }

    public function test_tidak_bisa_mengembalikan_aset_yang_sudah_dikembalikan(): void
    {
        $assetId = $this->createAsset();
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $this->actingAs($sysAdmin)->post("/admin/sistem/aset/{$assetId}/tugaskan", ['employee_id' => $this->employeeId('2018.03.0142')]);
        $assignmentId = DB::table('ast_assignments')->where('asset_id', $assetId)->value('id');
        $this->actingAs($sysAdmin)->post("/admin/sistem/penugasan-aset/{$assignmentId}/kembalikan", ['returned_condition' => 'baik']);

        $response = $this->actingAs($sysAdmin)->post("/admin/sistem/penugasan-aset/{$assignmentId}/kembalikan", ['returned_condition' => 'baik']);

        $response->assertSessionHas('gagal');
    }

    public function test_hr_admin_hanya_melihat_aset_kantornya_sendiri(): void
    {
        $gerungOffice = DB::table('emp_employees')->where('nrp', '2021.05.0302')->value('office_id');
        $otherOffice = DB::table('md_offices')->where('id', '!=', $gerungOffice)->value('id');

        $this->createAsset('LT-KANTOR-A', $gerungOffice);
        $this->createAsset('LT-KANTOR-B', $otherOffice);

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/admin/sistem/aset');

        $response->assertOk();
        $response->assertSeeText('LT-KANTOR-A');
        $response->assertDontSeeText('LT-KANTOR-B');
    }

    public function test_pegawai_melihat_aset_saya(): void
    {
        $assetId = $this->createAsset();
        $employeeId = $this->employeeId('2018.03.0142');
        $this->actingAs($this->userWithNrp('SYSADMIN'))->post("/admin/sistem/aset/{$assetId}/tugaskan", ['employee_id' => $employeeId]);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/aset-saya');

        $response->assertOk();
        $response->assertSeeText('Laptop Uji');
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/admin/sistem/aset');

        $response->assertForbidden();
    }

    private function createAsset(string $code = 'LT-UJI-9999', ?string $officeId = null): string
    {
        $officeId ??= DB::table('md_offices')->value('id');
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $this->actingAs($sysAdmin)->post('/admin/sistem/aset', [
            'asset_code' => $code, 'name' => 'Laptop Uji', 'category' => 'Laptop', 'office_id' => $officeId,
        ]);

        return DB::table('ast_assets')->where('asset_code', $code)->value('id');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        return User::query()->where('employee_id', $this->employeeId($nrp))->firstOrFail();
    }
}
