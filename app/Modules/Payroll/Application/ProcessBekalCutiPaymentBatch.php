<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

use App\Core\Domain\Money;
use App\Core\Domain\Uuid7;
use App\Modules\Payroll\Domain\Pph21Golongan;
use App\Modules\Payroll\Domain\SalaryScaleRepository;
use App\Modules\Payroll\Domain\SalaryStepNotFound;
use App\Modules\Payroll\Domain\TerRateNotFound;
use App\Modules\Payroll\Domain\TerRateRepository;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran Bekal Cuti MASSAL — alur SAMA seperti
 * ProcessOvertimePaymentBatch (checklist → pejabat pengusul → akun
 * jurnal → proses batch → cetak Memo Internal + Nota Debet), TAPI
 * pajaknya PPh 21 TER (bukan flat %):
 *
 * 1. Golongan PTKP pegawai (Pph21Golongan::fromStatus — SAMA persis
 *    yang dipakai payroll bulanan, RunPayrollDraft).
 * 2. Gaji kotor bulan berjalan = Imbalan Kerja (skala gaji) +
 *    Tunjangan Jabatan + Tunjangan Penyesuaian (SAMA rakitan
 *    RunPayrollDraft — BUKAN takeHomePartial yang sudah dipotong iuran).
 * 3. Bekal cuti BRUTO = 1× GAJI TERAKHIR (SK Direksi BPP/1087/03/64/2026
 *    Bab I §D angka 24 & Bab II §2.a — "Gaji" didefinisikan SEBAGAI
 *    imbalan kerja + tunjangan tetap, basis bekal cuti "1× gaji
 *    terakhir") — DIHITUNG OTOMATIS dari gaji kotor langkah 2, BUKAN
 *    diisi manual admin (beda dari asumsi awal sebelum SK ini
 *    diberikan — pay_bekal_cuti_disbursements.amount_cents memang
 *    NULL sampai sekarang karena rumusnya belum diimplementasikan,
 *    bukan karena tidak ada rumus resmi sama sekali).
 * 4. Penghasilan gabungan = gaji kotor + bekal cuti (= gaji kotor × 2,
 *    karena langkah 3) — dipakai HANYA untuk mencari tarif TER yang
 *    berlaku (golongan pegawai bisa naik lapisan karena bekal cuti
 *    menambah penghasilan bulan itu).
 * 5. Tarif TER dari langkah 4 diterapkan HANYA ke bekal cuti (bukan ke
 *    penghasilan gabungan) — gaji kotornya sendiri sudah/akan dipajaki
 *    terpisah lewat payroll bulanan, modul ini tidak menyentuhnya,
 *    tidak boleh memotong pajak dua kali atas komponen yang sama.
 *
 * Tidak ada guard "pejabat pengusul != approver" seperti lembur — baris
 * pay_bekal_cuti_disbursements TIDAK punya kolom approver sama sekali
 * (dipicu otomatis oleh persetujuan cuti CT, bukan diputuskan manusia
 * per-baris, lihat DisburseBekalCuti).
 */
final class ProcessBekalCutiPaymentBatch
{
    public function __construct(
        private readonly AuditRepository $audit,
        private readonly SalaryScaleRepository $salaryScale,
        private readonly TerRateRepository $terRates,
    ) {}

    /**
     * @param  array<int, string>  $disbursementIds
     * @return string id batch yang terbentuk
     */
    public function handle(
        array $disbursementIds,
        string $signatoryEmployeeId,
        string $leaveExpenseAccountId,
        string $taxExpenseAccountId,
        string $taxHoldingAccountId,
        string $payerScope,
        ?string $officeId,
        ?string $division,
        AuditActor $actor,
    ): string {
        if ($disbursementIds === []) {
            throw new DomainException('Pilih minimal satu pegawai untuk dibayar bekal cutinya.');
        }

        return DB::transaction(function () use ($disbursementIds, $signatoryEmployeeId, $leaveExpenseAccountId, $taxExpenseAccountId, $taxHoldingAccountId, $payerScope, $officeId, $division, $actor) {
            $now = new DateTimeImmutable;
            $asOf = AsOfDate::on($now);

            $items = [];
            $totalGross = 0;
            $totalTax = 0;
            $totalNet = 0;

            foreach ($disbursementIds as $disbursementId) {
                $disbursement = DB::table('pay_bekal_cuti_disbursements as b')
                    ->join('emp_employees as e', 'e.id', '=', 'b.employee_id')
                    ->where('b.id', $disbursementId)
                    ->select(
                        'b.id', 'b.status', 'e.id as employee_id', 'e.full_name', 'e.nomor_simpeda',
                        'e.person_grade', 'e.salary_step', 'e.tunjangan_jabatan_cents', 'e.tunjangan_penyesuaian_cents',
                        'e.marital_status', 'e.tanggungan',
                    )
                    ->lockForUpdate()
                    ->first();

                if ($disbursement === null) {
                    throw new DomainException('Salah satu baris Bekal Cuti tidak ditemukan.');
                }

                if ($disbursement->status !== 'pending') {
                    throw new DomainException("Bekal Cuti {$disbursement->full_name} sudah pernah dicairkan.");
                }

                $alreadyPaid = DB::table('bkl_payment_batch_items')->where('bekal_cuti_disbursement_id', $disbursementId)->exists();

                if ($alreadyPaid) {
                    throw new DomainException("Bekal Cuti {$disbursement->full_name} sudah pernah masuk batch pembayaran lain.");
                }

                if ($disbursement->marital_status === null) {
                    throw new DomainException("Status PTKP (status kawin) {$disbursement->full_name} belum diisi — lengkapi data pegawai sebelum bisa dihitung pajaknya.");
                }

                if ($disbursement->person_grade === null) {
                    throw new DomainException("{$disbursement->full_name} belum punya person grade — gaji kotor tidak bisa dihitung untuk basis tarif TER.");
                }

                $gajiKotor = $this->gajiKotorFor((int) $disbursement->person_grade, (int) $disbursement->salary_step, (int) $disbursement->tunjangan_jabatan_cents, (int) $disbursement->tunjangan_penyesuaian_cents, $asOf);

                $golongan = Pph21Golongan::fromStatus($disbursement->marital_status === 'menikah', (int) $disbursement->tanggungan);
                // Bekal cuti = 1x gaji terakhir (SK BPP/1087/03/64/2026) —
                // BUKAN nilai bebas, sama persis dengan gaji kotor di atas.
                $grossBekalCuti = $gajiKotor;
                $combinedIncome = $gajiKotor->add($grossBekalCuti);

                try {
                    $ratePercent = $this->terRates->ratePercentFor($golongan, $combinedIncome->cents, $asOf);
                } catch (TerRateNotFound $e) {
                    throw new DomainException("Tarif TER untuk {$disbursement->full_name}: {$e->getMessage()}");
                }

                // Tarif dicari dari PENGHASILAN GABUNGAN (langkah di atas),
                // tapi diterapkan HANYA ke bekal cuti — bukan ke gabungan —
                // supaya gaji kotor yang sudah/akan dipajaki lewat payroll
                // bulanan tidak terpotong pajak dua kali di sini.
                $tax = $grossBekalCuti->percentage($ratePercent);
                $net = $grossBekalCuti->subtract($tax);

                $items[] = [
                    'bekal_cuti_disbursement_id' => $disbursement->id,
                    'employee_id' => $disbursement->employee_id,
                    'gross_cents' => $grossBekalCuti->cents,
                    'gaji_kotor_cents' => $gajiKotor->cents,
                    'combined_income_cents' => $combinedIncome->cents,
                    'pph21_golongan' => $golongan->value,
                    'tax_rate_percent' => $ratePercent,
                    'tax_cents' => $tax->cents,
                    'net_cents' => $net->cents,
                    'bank_account_number' => $disbursement->nomor_simpeda,
                ];

                $totalGross += $grossBekalCuti->cents;
                $totalTax += $tax->cents;
                $totalNet += $net->cents;
            }

            $batchId = (string) Uuid7::generate();
            $referenceNumber = $this->nextReferenceNumber($now);

            DB::table('bkl_payment_batches')->insert([
                'id' => $batchId,
                'reference_number' => $referenceNumber,
                'payer_scope' => $payerScope,
                'office_id' => $officeId,
                'division' => $division,
                'signatory_employee_id' => $signatoryEmployeeId,
                'journal_leave_expense_account_id' => $leaveExpenseAccountId,
                'journal_tax_expense_account_id' => $taxExpenseAccountId,
                'journal_tax_holding_account_id' => $taxHoldingAccountId,
                'total_gross_cents' => $totalGross,
                'total_tax_cents' => $totalTax,
                'total_net_cents' => $totalNet,
                'created_by' => $actor->actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            foreach ($items as $item) {
                DB::table('bkl_payment_batch_items')->insert([
                    'id' => (string) Uuid7::generate(),
                    'batch_id' => $batchId,
                    ...$item,
                    'created_at' => $now,
                ]);

                DB::table('pay_bekal_cuti_disbursements')->where('id', $item['bekal_cuti_disbursement_id'])->update([
                    'amount_cents' => $item['gross_cents'],
                    'status' => 'disbursed',
                    'disbursed_by' => $actor->actorId,
                    'disbursed_at' => $now,
                    'disbursement_reference' => $referenceNumber,
                    'updated_at' => $now,
                ]);

                $this->audit->append(new AuditEntry(
                    occurredAt: $now,
                    actor: $actor,
                    auditableType: 'bekal_cuti_disbursement',
                    auditableId: $item['bekal_cuti_disbursement_id'],
                    action: AuditAction::Disbursed,
                    newValues: ['amount_cents' => $item['gross_cents']],
                    contextRef: $referenceNumber,
                ));
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'bkl_payment_batch',
                auditableId: $batchId,
                action: AuditAction::Created,
                newValues: [
                    'jumlah_pegawai' => count($items),
                    'total_gross_cents' => $totalGross,
                    'total_tax_cents' => $totalTax,
                    'total_net_cents' => $totalNet,
                ],
            ));

            return $batchId;
        });
    }

    /**
     * Pratinjau HANYA-BACA untuk tampilan antrean (sebelum batch
     * diproses) — supaya admin melihat jumlah bekal cuti yang akan
     * otomatis terisi (1× gaji terakhir) tanpa perlu memprosesnya
     * dulu. Null berarti data pegawai belum lengkap (grade/PTKP),
     * ditampilkan sebagai peringatan di halaman, BUKAN 0.
     */
    public function previewGajiKotorCents(?int $personGrade, ?int $salaryStep, int $tunjanganJabatanCents, int $tunjanganPenyesuaianCents): ?int
    {
        if ($personGrade === null) {
            return null;
        }

        try {
            return $this->gajiKotorFor($personGrade, $salaryStep ?? 1, $tunjanganJabatanCents, $tunjanganPenyesuaianCents, AsOfDate::on(new DateTimeImmutable))->cents;
        } catch (SalaryStepNotFound) {
            return null;
        }
    }

    private function gajiKotorFor(int $personGrade, int $salaryStep, int $tunjanganJabatanCents, int $tunjanganPenyesuaianCents, AsOfDate $asOf): Money
    {
        $imbalanKerja = $this->salaryScale->amountFor($personGrade, $salaryStep, $asOf);

        return $imbalanKerja
            ->add(Money::fromCents($tunjanganJabatanCents))
            ->add(Money::fromCents($tunjanganPenyesuaianCents));
    }

    private function nextReferenceNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('BKL-BAYAR/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('bkl_payment_batches')
            ->where('reference_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
