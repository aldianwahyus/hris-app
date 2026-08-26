<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Core\Domain\Uuid7;
use App\Interfaces\Http\Support\ComputeNavigationBadgeCounts;
use App\Models\User;
use App\Modules\Access\Contracts\CurrentActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Badge notifikasi sidebar — jumlah pending per antrean. Kelas yang
 * diuji MEMANGGIL `pendingCount()` yang sudah ada di tiap controller
 * antrean (satu sumber kebenaran per antrean, lihat
 * ComputeNavigationBadgeCounts), jadi tes ini cukup menegaskan angka
 * yang dikembalikan cocok dengan jumlah baris pending sesungguhnya —
 * bukan menguji ulang scoping AccessPolicy (sudah diuji di tes queue
 * masing-masing).
 */
final class NavigationBadgeCountsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_atasan_langsung_melihat_badge_lembur_pending(): void
    {
        $ahmad = $this->userWithNrp('2015.07.0088'); // atasan_langsung, KC-MTR
        $this->insertOvertimeRequest($this->employeeId('2018.03.0142'), 'pending'); // Siti, KC-MTR — bawahan Ahmad

        $counts = app(ComputeNavigationBadgeCounts::class)->forActor($this->actorFor($ahmad));

        $this->assertArrayHasKey('admin.approval-queue', $counts);
        $this->assertGreaterThanOrEqual(1, $counts['admin.approval-queue']);
    }

    public function test_role_tanpa_antrean_relevan_tidak_dapat_badge(): void
    {
        $siti = $this->userWithNrp('2018.03.0142'); // pegawai biasa, tidak punya peran approval/pembayaran apa pun

        $counts = app(ComputeNavigationBadgeCounts::class)->forActor($this->actorFor($siti));

        $this->assertSame([], $counts);
    }

    public function test_badge_hanya_hitung_yang_lebih_dari_nol_tidak_ada_entri_nol(): void
    {
        // Auditor tanpa data pending sama sekali — semua antrean nol,
        // hasil akhir harus array kosong (bukan array berisi angka 0).
        $budi = $this->userWithNrp('2020.01.0231'); // auditor

        $counts = app(ComputeNavigationBadgeCounts::class)->forActor($this->actorFor($budi));

        foreach ($counts as $n) {
            $this->assertGreaterThan(0, $n);
        }
    }

    public function test_admin_cabang_badge_pembayaran_lembur_cocok_jumlah_baris_approved_di_kantornya(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP-GRG
        $this->insertOvertimeRequest($this->employeeId('2021.05.0302'), 'approved', approverId: (string) Uuid7::generate());

        $counts = app(ComputeNavigationBadgeCounts::class)->forActor($this->actorFor($rina));

        $this->assertArrayHasKey('hr.overtime-disbursement.index', $counts);

        $expected = DB::table('ovt_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->where('r.status', 'approved')
            ->where('e.office_id', $this->employeeOfficeId('2021.05.0302'))
            ->count();

        $this->assertSame($expected, $counts['hr.overtime-disbursement.index']);
    }

    private function actorFor(User $user): CurrentActor
    {
        $this->actingAs($user);

        return app(CurrentActor::class);
    }

    private function insertOvertimeRequest(string $employeeId, string $status, ?string $approverId = null): string
    {
        $id = (string) Uuid7::generate();

        DB::table('ovt_requests')->insert([
            'id' => $id,
            'spkl_number' => 'SPKL/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'overtime_type' => 'crash_program',
            'work_date' => '2027-01-05',
            'amount_cents' => 25_000_000,
            'status' => $status,
            'approver_id' => $status === 'approved' ? $approverId : null,
            'decided_at' => $status === 'approved' ? now() : null,
            'approval_deadline' => '2027-02-04',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function employeeOfficeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('office_id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
