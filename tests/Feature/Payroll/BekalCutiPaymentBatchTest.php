<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pembayaran Bekal Cuti MASSAL — pola sama OvertimePaymentBatchTest,
 * tapi dua perbedaan besar: (1) jumlah bekal cuti bruto OTOMATIS = 1×
 * gaji terakhir (SK Direksi BPP/1087/03/64/2026), TIDAK diisi admin;
 * (2) pajaknya PPh 21 TER (bukan flat %): tarif dicari dari golongan
 * PTKP pegawai atas penghasilan gabungan (gaji kotor + bekal cuti,
 * yang berarti = gaji kotor × 2 karena (1)), diterapkan HANYA ke
 * bekal cuti.
 *
 * Skenario uji: pegawai grade 16 (gaji kotor Rp5.632.000, tunjangan
 * nol), TK/0 (golongan A) — bekal cuti bruto otomatis = Rp5.632.000
 * (sama dengan gaji kotor). Penghasilan gabungan Rp11.264.000 jatuh
 * di lapisan golongan A 11.050.000–11.600.000 (tarif 3,5%, lihat
 * pay_pph21_ter_rates seed). Pajak = 5.632.000 × 3,5% = Rp197.120.
 *
 * GROSS-UP (bukti dari Nota Debet/Lampiran resmi Bank NTB Syariah —
 * "Jumlah Diterima" pegawai SAMA dengan "Bekal Cuti" bruto, pajak
 * dibukukan terpisah, TIDAK memotong rekening pegawai): net_cents =
 * gross_cents, BUKAN gross-tax — beda dari Lembur/SPPD.
 */
final class BekalCutiPaymentBatchTest extends TestCase
{
    use DatabaseTransactions;

    private const GAJI_KOTOR_CENTS = 563_200_000; // grade 16 step 1, tunjangan 0

    private const EXPECTED_TAX_CENTS = 19_712_000; // 5.632.000 × 3,5%

    private const EXPECTED_NET_CENTS = self::GAJI_KOTOR_CENTS; // gross-up: pegawai terima UTUH bruto

    public function test_admin_hc_pilih_divisi_lalu_bayar_bekal_cuti_otomatis_1x_gaji_dengan_tarif_ter_benar(): void
    {
        $employeeId = $this->prepareEmployeeForTer('2014.02.0061', division: 'Human Capital');
        $disbursementId = $this->pendingBekalCutiFor($employeeId);
        [$bebanCutiId, $bebanPajakId, $penampunganId] = $this->seedJournalAccounts();

        $nurAisyah = $this->userWithNrp('2014.02.0061');

        $divisionList = $this->actingAs($nurAisyah)->get('/persetujuan/bekal-cuti');
        $divisionList->assertOk();
        $divisionList->assertSeeText('Human Capital');

        // Jumlah bekal cuti sudah terisi OTOMATIS di halaman checklist —
        // sebelum diproses, bukan diisi admin.
        $queue = $this->actingAs($nurAisyah)->get('/persetujuan/bekal-cuti?division=Human+Capital');
        $queue->assertOk();
        $queue->assertSeeText('Nur Aisyah');
        $queue->assertSeeText('Rp 5.632.000');

        $response = $this->actingAs($nurAisyah)->post('/persetujuan/bekal-cuti/bayar', [
            'division' => 'Human Capital',
            'disbursement_ids' => [$disbursementId],
            'signatory_employee_id' => $employeeId,
            'journal_leave_expense_account_id' => $bebanCutiId,
            'journal_tax_expense_account_id' => $bebanPajakId,
            'journal_tax_holding_account_id' => $penampunganId,
        ]);

        $response->assertSessionHas('sukses');
        $batchId = $this->batchIdFromRedirect($response);

        $batch = DB::table('bkl_payment_batches')->where('id', $batchId)->first();
        $this->assertNotNull($batch);
        $this->assertStringStartsWith('BKL-BAYAR/', $batch->reference_number);
        $this->assertSame('hc', $batch->payer_scope);
        $this->assertSame(self::GAJI_KOTOR_CENTS, $batch->total_gross_cents);
        $this->assertSame(self::EXPECTED_TAX_CENTS, $batch->total_tax_cents);
        $this->assertSame(self::EXPECTED_NET_CENTS, $batch->total_net_cents);

        $item = DB::table('bkl_payment_batch_items')->where('bekal_cuti_disbursement_id', $disbursementId)->first();
        $this->assertSame('A', $item->pph21_golongan);
        $this->assertSame('3.50', $item->tax_rate_percent);
        $this->assertSame(self::GAJI_KOTOR_CENTS, $item->gross_cents, 'Bekal cuti bruto harus SAMA dengan gaji kotor (1x gaji terakhir).');
        $this->assertSame(self::GAJI_KOTOR_CENTS, $item->gaji_kotor_cents);
        $this->assertSame(self::GAJI_KOTOR_CENTS * 2, $item->combined_income_cents);
        $this->assertSame(self::EXPECTED_TAX_CENTS, $item->tax_cents);
        $this->assertSame(self::EXPECTED_NET_CENTS, $item->net_cents);

        $row = DB::table('pay_bekal_cuti_disbursements')->where('id', $disbursementId)->first();
        $this->assertSame('disbursed', $row->status);
        $this->assertSame(self::GAJI_KOTOR_CENTS, $row->amount_cents);
        $this->assertSame($batch->reference_number, $row->disbursement_reference);

        foreach (['print-memo', 'print-nota-debet', 'print-lampiran-penerima'] as $suffix) {
            $print = $this->actingAs($nurAisyah)->get("/persetujuan/bekal-cuti/batch/{$batchId}/cetak/".str_replace('print-', '', $suffix));
            $print->assertOk();
            $this->assertSame('application/pdf', $print->headers->get('Content-Type'));
        }

        $show = $this->actingAs($nurAisyah)->get(route('admin.bekal-cuti-payment-batch.show', $batchId));
        $show->assertOk();
        $show->assertSeeText('3.50%');
    }

    public function test_admin_cabang_bayar_langsung_tanpa_tahap_divisi(): void
    {
        $employeeId = $this->prepareEmployeeForTer('2021.05.0302');
        $disbursementId = $this->pendingBekalCutiFor($employeeId);
        [$bebanCutiId, $bebanPajakId, $penampunganId] = $this->seedJournalAccounts();

        $rina = $this->userWithNrp('2021.05.0302');

        $queue = $this->actingAs($rina)->get('/pegawai/bekal-cuti');
        $queue->assertOk();
        $queue->assertSeeText('Rina Marlina');

        $response = $this->actingAs($rina)->post('/pegawai/bekal-cuti/bayar', [
            'disbursement_ids' => [$disbursementId],
            'signatory_employee_id' => $employeeId,
            'journal_leave_expense_account_id' => $bebanCutiId,
            'journal_tax_expense_account_id' => $bebanPajakId,
            'journal_tax_holding_account_id' => $penampunganId,
        ]);

        $response->assertSessionHas('sukses');
        $batchId = $this->batchIdFromRedirect($response);
        $this->assertSame('branch', DB::table('bkl_payment_batches')->where('id', $batchId)->value('payer_scope'));
    }

    public function test_bekal_cuti_yang_sudah_dibayar_tidak_bisa_dibayar_dua_kali(): void
    {
        $employeeId = $this->prepareEmployeeForTer('2021.05.0302');
        $disbursementId = $this->pendingBekalCutiFor($employeeId);
        [$bebanCutiId, $bebanPajakId, $penampunganId] = $this->seedJournalAccounts();
        $rina = $this->userWithNrp('2021.05.0302');

        $payload = [
            'disbursement_ids' => [$disbursementId],
            'signatory_employee_id' => $employeeId,
            'journal_leave_expense_account_id' => $bebanCutiId,
            'journal_tax_expense_account_id' => $bebanPajakId,
            'journal_tax_holding_account_id' => $penampunganId,
        ];

        $first = $this->actingAs($rina)->post('/pegawai/bekal-cuti/bayar', $payload);
        $first->assertSessionHas('sukses');

        DB::table('pay_bekal_cuti_disbursements')->where('id', $disbursementId)->update(['status' => 'pending']);

        $second = $this->actingAs($rina)->post('/pegawai/bekal-cuti/bayar', $payload);
        $second->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('bkl_payment_batch_items')->where('bekal_cuti_disbursement_id', $disbursementId)->count());
    }

    public function test_pegawai_tanpa_status_ptkp_ditolak_dengan_pesan_jelas(): void
    {
        $employeeId = $this->prepareEmployeeForTer('2021.05.0302');
        DB::table('emp_employees')->where('id', $employeeId)->update(['marital_status' => null]);
        $disbursementId = $this->pendingBekalCutiFor($employeeId);
        [$bebanCutiId, $bebanPajakId, $penampunganId] = $this->seedJournalAccounts();
        $rina = $this->userWithNrp('2021.05.0302');

        $response = $this->actingAs($rina)->post('/pegawai/bekal-cuti/bayar', [
            'disbursement_ids' => [$disbursementId],
            'signatory_employee_id' => $employeeId,
            'journal_leave_expense_account_id' => $bebanCutiId,
            'journal_tax_expense_account_id' => $bebanPajakId,
            'journal_tax_holding_account_id' => $penampunganId,
        ]);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('PTKP', (string) session('gagal'));
    }

    private function batchIdFromRedirect(TestResponse $response): string
    {
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        preg_match('~/batch/([0-9a-f-]{36})~', (string) $location, $m);
        $this->assertNotEmpty($m, "Tidak menemukan batch id di redirect: {$location}");

        return $m[1];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function seedJournalAccounts(): array
    {
        $bebanCutiId = (string) Uuid7::generate();
        $bebanPajakId = (string) Uuid7::generate();
        $penampunganId = (string) Uuid7::generate();
        $now = now();

        DB::table('fin_journal_accounts')->insert([
            ['id' => $bebanCutiId, 'code' => 'TEST-CUTI-'.uniqid(), 'name' => 'Beban Uang Cuti (Test)', 'category' => 'beban', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => $bebanPajakId, 'code' => 'TEST-PPH21-'.uniqid(), 'name' => 'Beban PPh 21 (Test)', 'category' => 'beban', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
            ['id' => $penampunganId, 'code' => 'TEST-PJK-'.uniqid(), 'name' => 'Penampungan Pajak (Test)', 'category' => 'penampungan_pajak', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now, 'version' => 1],
        ]);

        return [$bebanCutiId, $bebanPajakId, $penampunganId];
    }

    private function prepareEmployeeForTer(string $nrp, ?string $division = null): string
    {
        $employeeId = $this->employeeId($nrp);

        DB::table('emp_employees')->where('id', $employeeId)->update([
            'person_grade' => 16,
            'salary_step' => 1,
            'tunjangan_jabatan_cents' => 0,
            'tunjangan_penyesuaian_cents' => 0,
            'marital_status' => 'belum menikah',
            'tanggungan' => 0,
            'nomor_simpeda' => '0021001234567',
            'division' => $division,
        ]);

        return $employeeId;
    }

    private function pendingBekalCutiFor(string $employeeId): string
    {
        $leaveRequestId = (string) Uuid7::generate();
        $disbursementId = (string) Uuid7::generate();
        $now = now();

        DB::table('leave_requests')->insert([
            'id' => $leaveRequestId,
            'request_number' => 'CT/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'leave_type' => 'CT',
            'start_date' => $now->copy()->subDays(10)->toDateString(),
            'end_date' => $now->copy()->subDays(8)->toDateString(),
            'total_days' => 3,
            'status' => 'approved',
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        DB::table('pay_bekal_cuti_disbursements')->insert([
            'id' => $disbursementId,
            'employee_id' => $employeeId,
            'leave_request_id' => $leaveRequestId,
            'year' => (int) $now->format('Y'),
            'amount_cents' => null,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        return $disbursementId;
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
