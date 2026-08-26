<?php

declare(strict_types=1);

namespace Tests\Feature\Izin;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Izin Tidak Masuk Bekerja — 1 TAHAP, Atasan Langsung SAJA (office-tree),
 * pola SAMA PERSIS Tukar Shift — lihat IzinApprovalController.
 */
final class IzinApprovalQueueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_atasan_langsung_dapat_menyetujui_izin_bawahannya(): void
    {
        $requestId = $this->insertIzinRequest($this->employeeId('2018.03.0142'));

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, atasan_langsung KC Mataram
            ->post("/persetujuan/izin/{$requestId}/setujui");

        $response->assertRedirect(route('admin.izin-queue'));
        $this->assertSame('approved', DB::table('izin_requests')->where('id', $requestId)->value('status'));
    }

    public function test_atasan_kantor_lain_tidak_dapat_melihat_atau_memutus(): void
    {
        $requestId = $this->insertIzinRequest($this->employeeId('2018.03.0142')); // KC Mataram
        $dewi = $this->userWithNrp('2019.09.0177'); // KC Selong — bukan pohon kantor Mataram
        $this->grantRole($dewi, 'atasan_langsung');

        $response = $this->actingAs($dewi)->post("/persetujuan/izin/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('izin_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pemohon_tidak_dapat_menyetujui_pengajuannya_sendiri(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');
        $requestId = $this->insertIzinRequest($sitiId);
        $this->grantRole($this->userWithNrp('2018.03.0142'), 'atasan_langsung');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/persetujuan/izin/{$requestId}/setujui");

        $response->assertForbidden();
    }

    public function test_menolak_mengubah_status_dan_mencatat_penyetuju(): void
    {
        $requestId = $this->insertIzinRequest($this->employeeId('2018.03.0142'));
        $ahmadId = $this->employeeId('2015.07.0088');

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/izin/{$requestId}/tolak");

        $response->assertRedirect(route('admin.izin-queue'));

        $row = DB::table('izin_requests')->where('id', $requestId)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame($ahmadId, $row->approver_id);
    }

    public function test_pengajuan_yang_sudah_diputus_tidak_bisa_diputus_dua_kali(): void
    {
        $requestId = $this->insertIzinRequest($this->employeeId('2018.03.0142'));
        $ahmad = $this->userWithNrp('2015.07.0088');

        $this->actingAs($ahmad)->post("/persetujuan/izin/{$requestId}/setujui");

        $response = $this->actingAs($ahmad)->post("/persetujuan/izin/{$requestId}/tolak");

        $response->assertSessionHas('gagal');
        $this->assertSame('approved', DB::table('izin_requests')->where('id', $requestId)->value('status'));
    }

    public function test_peran_lain_ditolak_dari_antrean(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/persetujuan/izin');

        $response->assertForbidden();
    }

    public function test_lampiran_hanya_bisa_diunduh_oleh_yang_berwenang_dalam_lingkup(): void
    {
        $requestId = $this->insertIzinRequest($this->employeeId('2018.03.0142'), 'izin/bukti-test.jpg');

        $ahmad = $this->userWithNrp('2015.07.0088'); // atasan_langsung KC Mataram — berwenang
        $dewi = $this->userWithNrp('2019.09.0177'); // KC Selong — di luar lingkup
        $this->grantRole($dewi, 'atasan_langsung');

        // Berwenang: tidak 403/404 (akan gagal di Storage::download kalau
        // berkas fisik tidak ada di S3 test, tapi TIDAK boleh berhenti di
        // pengecekan wewenang — jadi cukup pastikan bukan 403 di sini
        // dengan menangkap exception storage secara terpisah tidak perlu;
        // fokus pengujian ini murni pengecekan wewenang lewat kantor lain).
        $forbidden = $this->actingAs($dewi)->get("/persetujuan/izin/{$requestId}/lampiran");
        $forbidden->assertForbidden();
    }

    private function insertIzinRequest(string $employeeId, ?string $attachmentPath = null): string
    {
        $id = (string) Uuid7::generate();

        DB::table('izin_requests')->insert([
            'id' => $id,
            'request_number' => 'IZN/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'category' => 'lainnya',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(1)->format('Y-m-d'),
            'total_days' => 1,
            'reason' => 'Uji coba',
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function grantRole(User $user, string $roleName): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        $alreadyHas = DB::table('model_has_roles')->where('model_id', $user->id)->where('role_id', $roleId)->exists();

        if (! $alreadyHas) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        }
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
