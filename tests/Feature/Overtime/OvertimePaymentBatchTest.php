<?php

declare(strict_types=1);

namespace Tests\Feature\Overtime;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pembayaran Lembur MASSAL — menggantikan alur satu-per-satu lama
 * (bekas OvertimeDisbursementTest, dihapus). Satu batch mencakup
 * banyak pengajuan `approved` sekaligus, menghasilkan SPKL-BAYAR baru
 * + breakdown gross/pajak/net per pegawai sesuai OVT_TAX_RATE_PERCENT
 * (placeholder 5% dari migrasi seed).
 */
final class OvertimePaymentBatchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_hc_pilih_divisi_lalu_bayar_massal_menghasilkan_spkl_baru_dan_pajak_benar(): void
    {
        $this->setDivision('2014.02.0061', 'Human Capital');
        [$bebanId, $pajakId] = $this->seedJournalAccounts();

        $requestId = $this->approvedOvertimeFor('2014.02.0061', approverId: (string) Uuid7::generate());
        $nurAisyah = $this->userWithNrp('2014.02.0061');
        $nurAisyahEmployeeId = $this->employeeId('2014.02.0061');

        // Tahap 1: tanpa ?division= — daftar divisi.
        $divisionList = $this->actingAs($nurAisyah)->get('/persetujuan/lembur-pembayaran');
        $divisionList->assertOk();
        $divisionList->assertSeeText('Human Capital');

        // Tahap 2: dengan ?division= — checklist + dropdown pejabat/jurnal.
        $queue = $this->actingAs($nurAisyah)->get('/persetujuan/lembur-pembayaran?division=Human+Capital');
        $queue->assertOk();
        $queue->assertSeeText('Nur Aisyah');

        $response = $this->actingAs($nurAisyah)->post('/persetujuan/lembur-pembayaran/bayar', [
            'division' => 'Human Capital',
            'request_ids' => [$requestId],
            'signatory_employee_id' => $nurAisyahEmployeeId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_account_id' => $pajakId,
        ]);

        $response->assertSessionHas('sukses');
        $batchId = $this->batchIdFromRedirect($response);

        $batch = DB::table('ovt_payment_batches')->where('id', $batchId)->first();
        $this->assertNotNull($batch);
        $this->assertStringStartsWith('SPKL-BAYAR/', $batch->spkl_number);
        $this->assertSame('hc', $batch->payer_scope);
        $this->assertSame('5.00', $batch->tax_rate_percent);
        $this->assertSame(25_000_000, $batch->total_gross_cents);
        $this->assertSame(1_250_000, $batch->total_tax_cents);
        $this->assertSame(23_750_000, $batch->total_net_cents);

        $item = DB::table('ovt_payment_batch_items')->where('ovt_request_id', $requestId)->first();
        $this->assertSame(25_000_000, $item->gross_cents);
        $this->assertSame(1_250_000, $item->tax_cents);
        $this->assertSame(23_750_000, $item->net_cents);

        $row = DB::table('ovt_requests')->where('id', $requestId)->first();
        $this->assertSame('disbursed', $row->status);
        $this->assertSame($batch->spkl_number, $row->disbursement_reference);

        // Tiga dokumen cetak harus bisa diunduh (PDF, bukan 500).
        foreach (['print-memo', 'print-nota-debet', 'print-jurnal-slip'] as $suffix) {
            $print = $this->actingAs($nurAisyah)->get("/persetujuan/lembur-pembayaran/batch/{$batchId}/cetak/".str_replace('print-', '', $suffix));
            $print->assertOk();
            $this->assertSame('application/pdf', $print->headers->get('Content-Type'));
        }

        // Halaman ringkasan batch — tombol cetak sekarang membuka modal
        // (dialog + iframe) berisi pratinjau PDF, bukan tab baru.
        $show = $this->actingAs($nurAisyah)->get(route('admin.overtime-payment-batch.show', $batchId));
        $show->assertOk();
        $show->assertSee('modal-cetak', false);
        $show->assertSee('bukaCetak', false);
    }

    public function test_admin_cabang_bayar_langsung_tanpa_tahap_divisi(): void
    {
        [$bebanId, $pajakId] = $this->seedJournalAccounts();

        $requestId = $this->approvedOvertimeFor('2021.05.0302', approverId: (string) Uuid7::generate());
        $rina = $this->userWithNrp('2021.05.0302');
        $rinaEmployeeId = $this->employeeId('2021.05.0302');

        $queue = $this->actingAs($rina)->get('/pegawai/lembur-pembayaran');
        $queue->assertOk();
        $queue->assertSeeText('Rina Marlina');

        $response = $this->actingAs($rina)->post('/pegawai/lembur-pembayaran/bayar', [
            'request_ids' => [$requestId],
            'signatory_employee_id' => $rinaEmployeeId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_account_id' => $pajakId,
        ]);

        $response->assertSessionHas('sukses');
        $batchId = $this->batchIdFromRedirect($response);

        $batch = DB::table('ovt_payment_batches')->where('id', $batchId)->first();
        $this->assertSame('branch', $batch->payer_scope);

        $row = DB::table('ovt_requests')->where('id', $requestId)->first();
        $this->assertSame('disbursed', $row->status);
    }

    public function test_pejabat_pengusul_tidak_boleh_sama_dengan_penyetuju(): void
    {
        [$bebanId, $pajakId] = $this->seedJournalAccounts();

        $ahmadEmployeeId = $this->employeeId('2015.07.0088');
        $requestId = $this->approvedOvertimeFor('2018.03.0142', approverId: $ahmadEmployeeId);

        // Pinjam sementara peran hr_admin untuk Ahmad supaya bisa mengakses
        // rute pembayaran cabang miliknya sendiri (KC-MTR), hanya untuk
        // menguji guard swa-cair — pola sama bekas OvertimeDisbursementTest.
        DB::table('model_has_roles')->insert([
            'role_id' => DB::table('roles')->where('name', 'hr_admin')->value('id'),
            'model_type' => User::class,
            'model_id' => $this->userWithNrp('2015.07.0088')->id,
        ]);
        $ahmad = $this->userWithNrp('2015.07.0088');

        $response = $this->actingAs($ahmad)->post('/pegawai/lembur-pembayaran/bayar', [
            'request_ids' => [$requestId],
            'signatory_employee_id' => $ahmadEmployeeId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_account_id' => $pajakId,
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame('approved', DB::table('ovt_requests')->where('id', $requestId)->value('status'));
    }

    /**
     * Bug ditemukan lewat audit kode, diperbaiki hari ini: guard lama
     * hanya membandingkan approver_id dengan NAMA PEJABAT PENGUSUL di
     * kertas (signatory_employee_id, bebas dipilih dari dropdown) — TIDAK
     * PERNAH memeriksa aktor yang benar-benar login dan memproses batch.
     * Test ini menyalakan celahnya: signatory dipilih orang LAIN (Rina),
     * tapi aktor yang benar-benar mengirim permintaan (Ahmad) adalah
     * approver_id pengajuan itu sendiri — harus tetap ditolak.
     */
    public function test_aktor_yang_memproses_batch_tidak_boleh_sama_dengan_penyetuju_meski_signatory_beda(): void
    {
        [$bebanId, $pajakId] = $this->seedJournalAccounts();

        $ahmadEmployeeId = $this->employeeId('2015.07.0088');
        $rinaEmployeeId = $this->employeeId('2021.05.0302');
        $requestId = $this->approvedOvertimeFor('2018.03.0142', approverId: $ahmadEmployeeId);

        DB::table('model_has_roles')->insert([
            'role_id' => DB::table('roles')->where('name', 'hr_admin')->value('id'),
            'model_type' => User::class,
            'model_id' => $this->userWithNrp('2015.07.0088')->id,
        ]);
        $ahmad = $this->userWithNrp('2015.07.0088');

        $response = $this->actingAs($ahmad)->post('/pegawai/lembur-pembayaran/bayar', [
            'request_ids' => [$requestId],
            'signatory_employee_id' => $rinaEmployeeId, // BUKAN Ahmad — lolos guard lama
            'journal_expense_account_id' => $bebanId,
            'journal_tax_account_id' => $pajakId,
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame('approved', DB::table('ovt_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pengajuan_yang_sudah_dibayar_tidak_bisa_dibayar_dua_kali(): void
    {
        [$bebanId, $pajakId] = $this->seedJournalAccounts();

        $requestId = $this->approvedOvertimeFor('2021.05.0302', approverId: (string) Uuid7::generate());
        $rina = $this->userWithNrp('2021.05.0302');
        $rinaEmployeeId = $this->employeeId('2021.05.0302');

        $first = $this->actingAs($rina)->post('/pegawai/lembur-pembayaran/bayar', [
            'request_ids' => [$requestId],
            'signatory_employee_id' => $rinaEmployeeId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_account_id' => $pajakId,
        ]);
        $first->assertSessionHas('sukses');

        // Coba lagi dengan request_id yang sama, dipaksa lewat status
        // approved lagi (skenario data dimanipulasi) — harus ditolak
        // karena sudah ada di ovt_payment_batch_items.
        DB::table('ovt_requests')->where('id', $requestId)->update(['status' => 'approved']);

        $second = $this->actingAs($rina)->post('/pegawai/lembur-pembayaran/bayar', [
            'request_ids' => [$requestId],
            'signatory_employee_id' => $rinaEmployeeId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_account_id' => $pajakId,
        ]);

        $second->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('ovt_payment_batch_items')->where('ovt_request_id', $requestId)->count());
    }

    public function test_admin_cabang_tidak_bisa_melihat_batch_pembayaran_kantor_pusat(): void
    {
        $this->setDivision('2014.02.0061', 'Human Capital');
        [$bebanId, $pajakId] = $this->seedJournalAccounts();

        $requestId = $this->approvedOvertimeFor('2014.02.0061', approverId: (string) Uuid7::generate());
        $nurAisyah = $this->userWithNrp('2014.02.0061');
        $nurAisyahEmployeeId = $this->employeeId('2014.02.0061');

        $response = $this->actingAs($nurAisyah)->post('/persetujuan/lembur-pembayaran/bayar', [
            'division' => 'Human Capital',
            'request_ids' => [$requestId],
            'signatory_employee_id' => $nurAisyahEmployeeId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_account_id' => $pajakId,
        ]);
        $batchId = $this->batchIdFromRedirect($response);

        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, cabang — BUKAN hr_approver.

        $this->actingAs($rina)->get("/persetujuan/lembur-pembayaran/batch/{$batchId}")->assertNotFound();
        $this->actingAs($rina)->get("/persetujuan/lembur-pembayaran/batch/{$batchId}/cetak/memo")->assertNotFound();
    }

    private function batchIdFromRedirect(TestResponse $response): string
    {
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        preg_match('~/batch/([0-9a-f-]{36})~', (string) $location, $m);
        $this->assertNotEmpty($m, "Tidak menemukan batch id di redirect: {$location}");

        return $m[1];
    }

    private function seedJournalAccounts(): array
    {
        $bebanId = (string) Uuid7::generate();
        $pajakId = (string) Uuid7::generate();

        DB::table('fin_journal_accounts')->insert([
            [
                'id' => $bebanId, 'code' => 'TEST-BEB-'.uniqid(), 'name' => 'IA Beban Uang Lembur (Test)',
                'category' => 'beban', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
            ],
            [
                'id' => $pajakId, 'code' => 'TEST-PJK-'.uniqid(), 'name' => 'Penampungan Pajak (Test)',
                'category' => 'penampungan_pajak', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
            ],
        ]);

        return [$bebanId, $pajakId];
    }

    private function setDivision(string $nrp, string $division): void
    {
        DB::table('emp_employees')->where('nrp', $nrp)->update(['division' => $division]);
    }

    private function approvedOvertimeFor(string $nrp, string $approverId): string
    {
        $id = (string) Uuid7::generate();

        DB::table('ovt_requests')->insert([
            'id' => $id,
            'spkl_number' => 'SPKL/TEST/'.uniqid(),
            'employee_id' => $this->employeeId($nrp),
            'overtime_type' => 'crash_program',
            'work_date' => '2027-01-05',
            'amount_cents' => 25_000_000,
            'status' => 'approved',
            'approver_id' => $approverId,
            'decided_at' => now(),
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

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
