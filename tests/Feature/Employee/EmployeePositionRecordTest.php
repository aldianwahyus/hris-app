<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Application\DecideEmployeeProfileChange;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Record Pegawai" — laporan rincian posisi terakhir per bulan, MURNI
 * dari emp_position_history (dibentuk otomatis lewat backfill +
 * persetujuan SK Mutasi/Promosi, TIDAK ADA input manual). Lihat
 * catatan lengkap di EmployeePositionRecordController dan
 * DecideEmployeeProfileChange::recordPositionHistoryIfChanged().
 */
final class EmployeePositionRecordTest extends TestCase
{
    use DatabaseTransactions;

    public function test_backfill_menyimpan_posisi_terkini_sebagai_baris_awal(): void
    {
        $employeeId = $this->employeeId('2021.05.0302'); // Rina Marlina
        $employee = DB::table('emp_employees')->where('id', $employeeId)->first();

        $history = DB::table('emp_position_history')->where('employee_id', $employeeId)->first();

        $this->assertNotNull($history);
        $this->assertSame($employee->office_id, $history->office_id);
        $this->assertSame($employee->position_id, $history->position_id);
        $this->assertNull($history->decision_letter_id, 'Baris backfill tidak berasal dari SK — bukti proyeksi mundur, bukan riwayat asli.');
    }

    public function test_persetujuan_sk_mutasi_menambah_baris_riwayat_posisi_baru(): void
    {
        [$sk, $targetId, $targetOfficeId] = $this->ajukanDanSetujuiMutasiKeKcMataram();

        $latest = DB::table('emp_position_history')
            ->where('employee_id', $targetId)
            ->orderByDesc('effective_from')
            ->first();

        $this->assertSame($targetOfficeId, $latest->office_id);
        $this->assertSame($sk->id, $latest->decision_letter_id);
        $this->assertSame(2, DB::table('emp_position_history')->where('employee_id', $targetId)->count(), 'Backfill + 1 baris baru dari mutasi.');
    }

    public function test_persetujuan_yang_tidak_mengubah_field_posisi_tidak_menambah_riwayat(): void
    {
        // Sanksi TIDAK menyentuh office_id/position_id/person_grade/job_grade sama sekali.
        $rina = $this->userWithNrp('2021.05.0302');
        $targetId = $this->employeeId('2021.05.0302');

        $before = DB::table('emp_position_history')->where('employee_id', $targetId)->count();

        $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$targetId],
            'sk_type' => 'sanksi',
            'sk_number' => 'SK/SANKSI/2026',
            'sk_date' => now()->format('Y-m-d'),
            'description' => 'Uji sanksi, bukan mutasi.',
        ]);

        $after = DB::table('emp_position_history')->where('employee_id', $targetId)->count();
        $this->assertSame($before, $after, 'Sanksi bukan Mutasi/Promosi — tidak boleh menambah baris riwayat posisi.');
    }

    public function test_laporan_bulan_ini_mencerminkan_posisi_setelah_mutasi_disetujui(): void
    {
        $this->ajukanDanSetujuiMutasiKeKcMataram();

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))
            ->get('/record-pegawai?bulan='.now()->format('Y-m'));

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertSeeText('KC Mataram');
    }

    public function test_laporan_bulan_lalu_masih_menampilkan_posisi_sebelum_mutasi(): void
    {
        $this->ajukanDanSetujuiMutasiKeKcMataram();

        $bulanLalu = now()->subMonth()->format('Y-m');
        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))
            ->get("/record-pegawai?bulan={$bulanLalu}");

        $response->assertOk();
        $response->assertSeeText('Rina Marlina');
        $response->assertSeeText('KCP Gerung'); // kantor ASLI, sebelum mutasi — mutasi belum "terjadi" pada bulan lalu
    }

    public function test_hr_approver_dapat_mengakses_record_pegawai(): void
    {
        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get('/record-pegawai');

        $response->assertOk();
        $response->assertSeeText('Record Pegawai');
    }

    public function test_peran_lain_ditolak_dari_record_pegawai(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/record-pegawai');

        $response->assertForbidden();
    }

    /** @return array{0: object, 1: string, 2: string} [SK row, employee id, target office id] */
    private function ajukanDanSetujuiMutasiKeKcMataram(): array
    {
        $rina = $this->userWithNrp('2021.05.0302');
        $targetId = $this->employeeId('2021.05.0302');
        $targetOfficeId = DB::table('md_offices')->where('code', 'KC-MTR')->value('id');

        $this->actingAs($rina)->post('/sk', [
            'employee_ids' => [$targetId],
            'sk_type' => 'mutasi',
            'sk_number' => 'SK/POS/'.uniqid(),
            'sk_date' => now()->format('Y-m-d'),
            'description' => 'Mutasi ke KC Mataram.',
            'target_office_id' => $targetOfficeId,
        ]);

        $sk = DB::table('emp_decision_letters')->where('employee_id', $targetId)->latest('created_at')->first();

        app(DecideEmployeeProfileChange::class)->approve(
            $sk->profile_change_request_id,
            new AuditActor(actorId: $this->employeeId('2014.02.0061'), actorRole: 'hr_approver'),
        );

        return [$sk, $targetId, $targetOfficeId];
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
