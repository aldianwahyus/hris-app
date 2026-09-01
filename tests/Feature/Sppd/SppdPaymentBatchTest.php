<?php

declare(strict_types=1);

namespace Tests\Feature\Sppd;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Modules\Sppd\Application\SubmitSppdMemoGroup;
use App\Modules\Sppd\Domain\TripCategory;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pembayaran SPPD Massal — batch di-scope PER GRUP MEMO (bukan per
 * divisi seperti Lembur), TANPA guard pemisahan tugas gaya Lembur
 * (baris hasil memo tidak punya approver_id sama sekali — lihat
 * ProcessSppdPaymentBatch). SENGAJA TIDAK ADA test "signatory==approver"/
 * "actor==approver" gaya OvertimePaymentBatchTest — approver_id selalu
 * NULL pada baris ini, jadi guard semacam itu tidak mungkin diterapkan.
 */
final class SppdPaymentBatchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_hc_bayar_batch_untuk_memo_grup_hc_menghasilkan_batch_baru(): void
    {
        $bebanId = $this->seedJournalAccount();
        [$taxExpenseId, $taxAccountId] = $this->seedTaxAccounts();
        $sitiId = $this->employeeId('2018.03.0142');
        $budiId = $this->employeeId('2020.01.0231');
        $groupId = $this->submitMemoGroup([$sitiId, $budiId]);

        $nurAisyah = $this->userWithNrp('2014.02.0061');
        $nurAisyahId = $this->employeeId('2014.02.0061');

        $queue = $this->actingAs($nurAisyah)->get(route('admin.sppd-payment.queue', $groupId));
        $queue->assertOk();
        $queue->assertSeeText('Siti Rahmawati');
        $queue->assertSeeText('Budi Santoso');

        $requestIds = DB::table('spd_requests')->where('memo_group_id', $groupId)->pluck('id')->all();

        $response = $this->actingAs($nurAisyah)->post(route('admin.sppd-payment.process', $groupId), [
            'memo_group_id' => $groupId,
            'request_ids' => $requestIds,
            'signatory_employee_id' => $nurAisyahId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_expense_account_id' => $taxExpenseId,
            'journal_tax_account_id' => $taxAccountId,
        ]);

        $response->assertSessionHas('sukses');
        $batchId = $this->batchIdFromRedirect($response);

        $batch = DB::table('spd_payment_batches')->where('id', $batchId)->first();
        $this->assertNotNull($batch);
        $this->assertStringStartsWith('SPPD-BAYAR/', $batch->batch_number);
        $this->assertSame('hc', $batch->payer_scope);

        // team_leader tier, 2 hari, jarak_jauh_keluar_provinsi — per traveler:
        // makan 500.000 + saku 900.000 + hotel 2.000.000
        // + angkutan 500.000 + transportasi tujuan 750.000 (tidak dikali hari) = Rp4.650.000.
        // "hotel" dan "hotel_kompensasi" SALING MENGGANTIKAN (§II.B.6, ditegakkan
        // SubmitSppdMemoGroup::handle()) — tidak bisa dicentang berdua sekaligus.
        $expectedTotal = 2 * (500_000_00 + 900_000_00 + 2_000_000_00 + 500_000_00 + 750_000_00);
        $this->assertSame($expectedTotal, $batch->total_amount_cents);
        $this->assertSame(2, DB::table('spd_payment_batch_items')->where('batch_id', $batchId)->count());

        foreach ([$sitiId, $budiId] as $employeeId) {
            $row = DB::table('spd_requests')->where('employee_id', $employeeId)->where('memo_group_id', $groupId)->first();
            $this->assertSame('disbursed', $row->status);
            $this->assertSame($batch->batch_number, $row->disbursement_reference);
        }

        foreach (['nota-debet', 'jurnal-slip'] as $doc) {
            $print = $this->actingAs($nurAisyah)->get(route("admin.sppd-payment-batch.print-{$doc}", $batchId));
            $print->assertOk();
            $this->assertSame('application/pdf', $print->headers->get('Content-Type'));
        }
    }

    public function test_admin_cabang_bayar_batch_untuk_memo_grup_cabang_miliknya(): void
    {
        $bebanId = $this->seedJournalAccount();
        [$taxExpenseId, $taxAccountId] = $this->seedTaxAccounts();
        $this->grantHrAdminTo('2015.07.0088'); // Ahmad Fauzi, KC-MTR
        $ahmad = $this->userWithNrp('2015.07.0088');
        $ahmadId = $this->employeeId('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142'); // KC-MTR — kantor sama dengan Ahmad

        $groupId = $this->submitMemoGroupAsBranchAdmin($ahmad, [$sitiId]);

        $requestIds = DB::table('spd_requests')->where('memo_group_id', $groupId)->pluck('id')->all();

        $response = $this->actingAs($ahmad)->post(route('hr.sppd-payment.process', $groupId), [
            'memo_group_id' => $groupId,
            'request_ids' => $requestIds,
            'signatory_employee_id' => $ahmadId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_expense_account_id' => $taxExpenseId,
            'journal_tax_account_id' => $taxAccountId,
        ]);

        $response->assertSessionHas('sukses');
        $batchId = $this->batchIdFromRedirect($response);

        $batch = DB::table('spd_payment_batches')->where('id', $batchId)->first();
        $this->assertSame('branch', $batch->payer_scope);
        $this->assertSame('disbursed', DB::table('spd_requests')->where('id', $requestIds[0])->value('status'));
    }

    public function test_admin_cabang_tidak_bisa_membayar_memo_grup_kantor_lain(): void
    {
        $bebanId = $this->seedJournalAccount();
        [$taxExpenseId, $taxAccountId] = $this->seedTaxAccounts();
        $this->grantHrAdminTo('2015.07.0088'); // Ahmad Fauzi, KC-MTR
        $ahmad = $this->userWithNrp('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142');
        $groupId = $this->submitMemoGroupAsBranchAdmin($ahmad, [$sitiId]);

        $requestIds = DB::table('spd_requests')->where('memo_group_id', $groupId)->pluck('id')->all();

        $this->grantHrAdminTo('2021.05.0302'); // Rina Marlina, KCP-GRG — kantor BEDA
        $rina = $this->userWithNrp('2021.05.0302');
        $rinaId = $this->employeeId('2021.05.0302');

        $this->actingAs($rina)->get(route('hr.sppd-payment.queue', $groupId))->assertNotFound();

        $response = $this->actingAs($rina)->post(route('hr.sppd-payment.process', $groupId), [
            'memo_group_id' => $groupId,
            'request_ids' => $requestIds,
            'signatory_employee_id' => $rinaId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_expense_account_id' => $taxExpenseId,
            'journal_tax_account_id' => $taxAccountId,
        ]);
        $response->assertNotFound();
    }

    public function test_pegawai_yang_sudah_dibayar_tidak_bisa_dibayar_dua_kali(): void
    {
        $bebanId = $this->seedJournalAccount();
        [$taxExpenseId, $taxAccountId] = $this->seedTaxAccounts();
        $sitiId = $this->employeeId('2018.03.0142');
        $groupId = $this->submitMemoGroup([$sitiId]);
        $nurAisyah = $this->userWithNrp('2014.02.0061');
        $nurAisyahId = $this->employeeId('2014.02.0061');
        $requestId = DB::table('spd_requests')->where('memo_group_id', $groupId)->value('id');

        $first = $this->actingAs($nurAisyah)->post(route('admin.sppd-payment.process', $groupId), [
            'memo_group_id' => $groupId,
            'request_ids' => [$requestId],
            'signatory_employee_id' => $nurAisyahId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_expense_account_id' => $taxExpenseId,
            'journal_tax_account_id' => $taxAccountId,
        ]);
        $first->assertSessionHas('sukses');

        // Skenario data dimanipulasi — paksa kembali ke approved.
        DB::table('spd_requests')->where('id', $requestId)->update(['status' => 'approved']);

        $second = $this->actingAs($nurAisyah)->post(route('admin.sppd-payment.process', $groupId), [
            'memo_group_id' => $groupId,
            'request_ids' => [$requestId],
            'signatory_employee_id' => $nurAisyahId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_expense_account_id' => $taxExpenseId,
            'journal_tax_account_id' => $taxAccountId,
        ]);
        $second->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('spd_payment_batch_items')->where('spd_request_id', $requestId)->count());
    }

    public function test_pegawai_dari_grup_memo_lain_tidak_bisa_dicampur_ke_satu_batch(): void
    {
        $bebanId = $this->seedJournalAccount();
        [$taxExpenseId, $taxAccountId] = $this->seedTaxAccounts();
        $sitiId = $this->employeeId('2018.03.0142');
        $budiId = $this->employeeId('2020.01.0231');

        $group1 = $this->submitMemoGroup([$sitiId]);
        $group2 = $this->submitMemoGroup([$budiId]);

        $nurAisyah = $this->userWithNrp('2014.02.0061');
        $nurAisyahId = $this->employeeId('2014.02.0061');

        $requestFromGroup1 = DB::table('spd_requests')->where('memo_group_id', $group1)->value('id');
        $requestFromGroup2 = DB::table('spd_requests')->where('memo_group_id', $group2)->value('id');

        $response = $this->actingAs($nurAisyah)->post(route('admin.sppd-payment.process', $group1), [
            'memo_group_id' => $group1,
            'request_ids' => [$requestFromGroup1, $requestFromGroup2], // dicampur — HARUS ditolak
            'signatory_employee_id' => $nurAisyahId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_expense_account_id' => $taxExpenseId,
            'journal_tax_account_id' => $taxAccountId,
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('spd_payment_batch_items')->count());
        $this->assertSame('approved', DB::table('spd_requests')->where('id', $requestFromGroup1)->value('status'));
        $this->assertSame('approved', DB::table('spd_requests')->where('id', $requestFromGroup2)->value('status'));
    }

    public function test_admin_cabang_tidak_bisa_melihat_batch_pembayaran_hc(): void
    {
        $bebanId = $this->seedJournalAccount();
        [$taxExpenseId, $taxAccountId] = $this->seedTaxAccounts();
        $sitiId = $this->employeeId('2018.03.0142');
        $groupId = $this->submitMemoGroup([$sitiId]);
        $nurAisyah = $this->userWithNrp('2014.02.0061');
        $nurAisyahId = $this->employeeId('2014.02.0061');
        $requestId = DB::table('spd_requests')->where('memo_group_id', $groupId)->value('id');

        $response = $this->actingAs($nurAisyah)->post(route('admin.sppd-payment.process', $groupId), [
            'memo_group_id' => $groupId,
            'request_ids' => [$requestId],
            'signatory_employee_id' => $nurAisyahId,
            'journal_expense_account_id' => $bebanId,
            'journal_tax_expense_account_id' => $taxExpenseId,
            'journal_tax_account_id' => $taxAccountId,
        ]);
        $batchId = $this->batchIdFromRedirect($response);

        $this->grantHrAdminTo('2021.05.0302');
        $rina = $this->userWithNrp('2021.05.0302');

        $this->actingAs($rina)->get(route('admin.sppd-payment-batch.show', $batchId))->assertNotFound();
        $this->actingAs($rina)->get(route('admin.sppd-payment-batch.print-nota-debet', $batchId))->assertNotFound();
    }

    /** @param array<int, string> $employeeIds */
    private function submitMemoGroup(array $employeeIds): string
    {
        $nurAisyahId = $this->employeeId('2014.02.0061');

        return app(SubmitSppdMemoGroup::class)->handle(
            employeeIds: $employeeIds,
            memoNumber: 'MEMO/HC/2026/09/'.uniqid(),
            memoDate: new DateTimeImmutable('2026-09-01'),
            sourceDivision: null,
            tripCategory: TripCategory::JarakJauhKeluarProvinsi,
            destination: 'Surabaya',
            purpose: 'Uji',
            startDate: new DateTimeImmutable('2026-09-10'),
            endDate: new DateTimeImmutable('2026-09-11'),
            radiusBand: null,
            // Komponen INTI saja (bukan SubmitSppdMemoGroup::COMPONENT_KEYS
            // penuh) — file test ini menguji mekanisme BATCH pembayaran,
            // bukan tiap varian komponen lumpsum, jadi angka total di sini
            // sengaja stabil dan tidak ikut berubah tiap kali ada komponen
            // opsional baru ditambahkan (lihat SppdMemoSubmissionTest untuk
            // pengujian komponen H-1/H+1 dan konsumsi sebagian).
            includedComponents: ['uang_makan', 'uang_saku', 'hotel', 'angkutan_setempat', 'transportasi_tujuan'],
            employeeComponentOptions: [],
            authorizingOfficialTitle: null,
            authorizingOfficialName: null,
            lumpsumSignatory1Title: null,
            lumpsumSignatory1Name: null,
            lumpsumSignatory2Title: null,
            lumpsumSignatory2Name: null,
            payerScope: 'hc',
            officeId: null,
            actor: new AuditActor(actorId: $nurAisyahId, actorRole: 'hr_approver'),
        );
    }

    /** @param array<int, string> $employeeIds */
    private function submitMemoGroupAsBranchAdmin(User $actorUser, array $employeeIds): string
    {
        $officeId = DB::table('emp_employees')->where('id', $actorUser->employee_id)->value('office_id');

        return app(SubmitSppdMemoGroup::class)->handle(
            employeeIds: $employeeIds,
            memoNumber: 'MEMO/CAB/2026/09/'.uniqid(),
            memoDate: new DateTimeImmutable('2026-09-01'),
            sourceDivision: null,
            tripCategory: TripCategory::JarakJauhDalamProvinsi,
            destination: 'Mataram',
            purpose: 'Uji',
            startDate: new DateTimeImmutable('2026-09-10'),
            endDate: new DateTimeImmutable('2026-09-10'),
            radiusBand: null,
            // Komponen INTI saja (bukan SubmitSppdMemoGroup::COMPONENT_KEYS
            // penuh) — file test ini menguji mekanisme BATCH pembayaran,
            // bukan tiap varian komponen lumpsum, jadi angka total di sini
            // sengaja stabil dan tidak ikut berubah tiap kali ada komponen
            // opsional baru ditambahkan (lihat SppdMemoSubmissionTest untuk
            // pengujian komponen H-1/H+1 dan konsumsi sebagian).
            includedComponents: ['uang_makan', 'uang_saku', 'hotel', 'angkutan_setempat', 'transportasi_tujuan'],
            employeeComponentOptions: [],
            authorizingOfficialTitle: null,
            authorizingOfficialName: null,
            lumpsumSignatory1Title: null,
            lumpsumSignatory1Name: null,
            lumpsumSignatory2Title: null,
            lumpsumSignatory2Name: null,
            payerScope: 'branch',
            officeId: $officeId,
            actor: new AuditActor(actorId: $actorUser->employee_id, actorRole: 'hr_admin'),
        );
    }

    private function seedJournalAccount(): string
    {
        $id = (string) Uuid7::generate();

        DB::table('fin_journal_accounts')->insert([
            'id' => $id,
            'code' => 'TEST-SPD-'.uniqid(),
            'name' => 'Beban SPPD Massal (Test)',
            'category' => 'beban',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    /** @return array{0: string, 1: string} [journal_tax_expense_account_id, journal_tax_account_id] */
    private function seedTaxAccounts(): array
    {
        $taxExpenseId = (string) Uuid7::generate();
        $taxAccountId = (string) Uuid7::generate();

        DB::table('fin_journal_accounts')->insert([
            [
                'id' => $taxExpenseId,
                'code' => 'TEST-PPH-'.uniqid(),
                'name' => 'Beban PPh 21 SPPD (Test)',
                'category' => 'beban',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ],
            [
                'id' => $taxAccountId,
                'code' => 'TEST-TMP-'.uniqid(),
                'name' => 'Penampungan Pajak SPPD (Test)',
                'category' => 'penampungan_pajak',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ],
        ]);

        return [$taxExpenseId, $taxAccountId];
    }

    private function batchIdFromRedirect(TestResponse $response): string
    {
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        preg_match('~/batch/([0-9a-f-]{36})~', (string) $location, $m);
        $this->assertNotEmpty($m, "Tidak menemukan batch id di redirect: {$location}");

        return $m[1];
    }

    private function grantHrAdminTo(string $nrp): void
    {
        $alreadyHrAdmin = DB::table('model_has_roles')
            ->where('model_id', $this->userWithNrp($nrp)->id)
            ->where('role_id', DB::table('roles')->where('name', 'hr_admin')->value('id'))
            ->exists();

        if ($alreadyHrAdmin) {
            return;
        }

        DB::table('model_has_roles')->insert([
            'role_id' => DB::table('roles')->where('name', 'hr_admin')->value('id'),
            'model_type' => User::class,
            'model_id' => $this->userWithNrp($nrp)->id,
        ]);
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
