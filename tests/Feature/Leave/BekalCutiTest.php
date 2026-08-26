<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\LeaveType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bekal Cuti — dipicu OTOMATIS saat Cuti Tahunan disetujui tahap 2
 * (pertama kali pegawai memakai jatah tahun berjalan), amount_cents
 * diisi manual saat pencairan (tidak ada rumus resmi terverifikasi).
 */
final class BekalCutiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bekal_cuti_muncul_setelah_ct_pertama_disetujui_tahap_2(): void
    {
        // Ahmad Fauzi (KC Mataram) belum pernah cuti tahun ini —
        // 2026-09-01 (Selasa) s.d. 2026-09-07 (Senin) = 5 HARI KERJA
        // murni (melompati akhir pekan) memenuhi blok minimal
        // pengambilan pertama (lihat SubmitLeaveRequestTest — total_days
        // sekarang dihitung sebagai hari kerja, bukan kalender mentah).
        $ahmadId = $this->employeeId('2015.07.0088');

        $requestNumber = app(SubmitLeaveRequest::class)->handle(
            employeeId: $ahmadId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-07'),
            reason: null,
            actor: new AuditActor(actorId: $ahmadId, actorRole: 'pegawai'),
        );
        $requestId = DB::table('leave_requests')->where('request_number', $requestNumber)->value('id');

        $this->assertSame(0, DB::table('pay_bekal_cuti_disbursements')->where('employee_id', $ahmadId)->count());

        // Ahmad tidak bisa memutus pengajuannya sendiri, dan satu orang
        // tidak boleh memutus KEDUA tahap (guard swa-putus lintas-tahap)
        // — pinjam DUA orang berbeda di KC Mataram, masing-masing SATU
        // peran SEMENTARA khusus test ini.
        $this->grantRole('2018.03.0142', 'atasan_langsung'); // Siti — tahap 1
        $this->grantRole('2017.11.0119', 'pimpinan_kantor'); // Hendra — tahap 2
        $siti = $this->userWithNrp('2018.03.0142');
        $hendra = $this->userWithNrp('2017.11.0119');

        $this->actingAs($siti)->post("/persetujuan/cuti/{$requestId}/setujui"); // tahap 1
        $this->assertSame('pending_pimpinan', DB::table('leave_requests')->where('id', $requestId)->value('status'));

        $response = $this->actingAs($hendra)->post("/persetujuan/cuti/{$requestId}/setujui"); // tahap 2
        $response->assertRedirect(route('admin.leave-approval-queue'));
        $this->assertSame('approved', DB::table('leave_requests')->where('id', $requestId)->value('status'));

        $bekalCuti = DB::table('pay_bekal_cuti_disbursements')->where('employee_id', $ahmadId)->first();
        $this->assertNotNull($bekalCuti);
        $this->assertSame($requestId, $bekalCuti->leave_request_id);
        $this->assertSame((int) date('Y'), $bekalCuti->year);
        $this->assertSame('pending', $bekalCuti->status);
        $this->assertNull($bekalCuti->amount_cents);
    }

    public function test_bekal_cuti_tidak_dibuat_dua_kali_tahun_yang_sama(): void
    {
        $ahmadId = $this->employeeId('2015.07.0088');
        $year = (int) date('Y');

        DB::table('pay_bekal_cuti_disbursements')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $ahmadId,
            'leave_request_id' => $this->seedApprovedCtRequest($ahmadId),
            'year' => $year,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        DB::table('leave_balances')
            ->where('employee_id', $ahmadId)->where('year', $year)->where('bucket_type', 'current_year')
            ->update(['used_days' => 5]);

        $secondRequestId = $this->seedPendingPimpinanCtRequest($ahmadId);

        $this->grantRole('2018.03.0142', 'pimpinan_kantor');
        $siti = $this->userWithNrp('2018.03.0142');
        $this->actingAs($siti)->post("/persetujuan/cuti/{$secondRequestId}/setujui");

        $this->assertSame(
            1,
            DB::table('pay_bekal_cuti_disbursements')->where('employee_id', $ahmadId)->where('year', $year)->count(),
            'Bekal Cuti hanya SEKALI per pegawai per tahun.'
        );
    }

    /**
     * Pencairan sekarang batch (pola sama Pembayaran Lembur, lihat
     * ProcessBekalCutiPaymentBatch/BekalCutiPaymentBatchTest) — bukan
     * lagi satu-per-satu dengan jumlah+referensi diketik bebas. Tes
     * detail perhitungan pajak TER ada di BekalCutiPaymentBatchTest;
     * di sini cukup memastikan rute batch Admin Cabang benar-benar
     * memindahkan status baris ini ke disbursed.
     */
    public function test_hr_admin_dapat_mencairkan_bekal_cuti_kantornya(): void
    {
        $rinaId = $this->employeeId('2021.05.0302'); // hr_admin, KCP Gerung

        DB::table('emp_employees')->where('id', $rinaId)->update([
            'person_grade' => 1,
            'salary_step' => 1,
            'tunjangan_jabatan_cents' => 0,
            'tunjangan_penyesuaian_cents' => 0,
            'marital_status' => 'belum menikah',
            'tanggungan' => 0,
        ]);

        DB::table('pay_bekal_cuti_disbursements')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $rinaId,
            'leave_request_id' => $this->seedApprovedCtRequest($rinaId),
            'year' => (int) date('Y'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $bekalCutiId = DB::table('pay_bekal_cuti_disbursements')->where('employee_id', $rinaId)->value('id');
        $rina = $this->userWithNrp('2021.05.0302');

        $now = now();
        [$bebanCutiId, $bebanPajakId, $penampunganId] = [(string) Uuid7::generate(), (string) Uuid7::generate(), (string) Uuid7::generate()];
        DB::table('fin_journal_accounts')->insert([
            ['id' => $bebanCutiId, 'code' => 'TEST-CUTI-'.uniqid(), 'name' => 'Beban Uang Cuti (Test)', 'category' => 'beban', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => $bebanPajakId, 'code' => 'TEST-PPH21-'.uniqid(), 'name' => 'Beban PPh 21 (Test)', 'category' => 'beban', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => $penampunganId, 'code' => 'TEST-PJK-'.uniqid(), 'name' => 'Penampungan Pajak (Test)', 'category' => 'penampungan_pajak', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
        ]);

        $response = $this->actingAs($rina)->post('/pegawai/bekal-cuti/bayar', [
            'disbursement_ids' => [$bekalCutiId],
            'signatory_employee_id' => $rinaId,
            'journal_leave_expense_account_id' => $bebanCutiId,
            'journal_tax_expense_account_id' => $bebanPajakId,
            'journal_tax_holding_account_id' => $penampunganId,
        ]);

        $response->assertSessionHas('sukses');

        $row = DB::table('pay_bekal_cuti_disbursements')->where('id', $bekalCutiId)->first();
        $this->assertSame('disbursed', $row->status);
        // Jumlah bekal cuti OTOMATIS = 1x gaji terakhir (grade 1 step 1,
        // tunjangan nol) — BUKAN diisi manual seperti tes lama.
        $this->assertSame(104_200_000, $row->amount_cents);
        $this->assertNotNull($row->disbursement_reference);
        $this->assertStringStartsWith('BKL-BAYAR/', $row->disbursement_reference);
    }

    private function seedApprovedCtRequest(string $employeeId): string
    {
        $id = (string) Uuid7::generate();

        DB::table('leave_requests')->insert([
            'id' => $id,
            'request_number' => 'CT/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'leave_type' => 'CT',
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-05',
            'total_days' => 5,
            'status' => 'approved',
            'approver_id' => (string) Uuid7::generate(),
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function seedPendingPimpinanCtRequest(string $employeeId): string
    {
        $id = (string) Uuid7::generate();

        DB::table('leave_requests')->insert([
            'id' => $id,
            'request_number' => 'CT/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'leave_type' => 'CT',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-01',
            'total_days' => 1,
            'status' => 'pending_pimpinan',
            'atasan_approver_id' => (string) Uuid7::generate(),
            'atasan_decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function grantRole(string $nrp, string $roleName): void
    {
        $userId = $this->userWithNrp($nrp)->id;
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        $alreadyHas = DB::table('model_has_roles')
            ->where('model_id', $userId)->where('role_id', $roleId)->exists();

        if (! $alreadyHas) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $userId,
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
