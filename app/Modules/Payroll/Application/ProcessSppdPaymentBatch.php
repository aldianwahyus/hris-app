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
 * Pembayaran SPPD Massal — batch di-scope per GRUP MEMO
 * (spd_memo_groups), bukan per divisi seperti Lembur, karena grup memo
 * itu sendiri sudah jadi satuan alami pembayaran ("setelah berkas
 * kembali, bayar batch-nya").
 *
 * Guard gaya BEKAL CUTI, BUKAN gaya Lembur (ProcessOvertimePaymentBatch):
 * setiap baris spd_requests hasil memo (SubmitSppdMemoGroup) punya
 * approver_id=NULL — TIDAK ADA approver manusia untuk dibandingkan
 * dengan signatoryEmployeeId/actor->actorId, jadi guard pemisahan tugas
 * gaya Lembur tidak bisa diterapkan di sini (tidak ada apa pun untuk
 * dibandingkan). Ini SESUAI keputusan bisnis: memo itu sendiri meniadakan
 * pemisahan maker/checker untuk jalur ini (lihat SubmitSppdMemoGroup).
 * ProcessBekalCutiPaymentBatch adalah preseden persis untuk situasi
 * "baris tanpa approver_id sama sekali → tanpa guard pemisahan tugas".
 *
 * DIKENAKAN PPh 21 metode TER — dibuktikan dari dokumen Nota Debet/
 * Jurnal Slip resmi Bank NTB Syariah (bug ditemukan lewat perbandingan
 * dengan dokumen resmi: migrasi awal keliru mengasumsikan "SPPD tidak
 * punya pemotongan pajak"). Mekanisme SAMA PERSIS
 * ProcessBekalCutiPaymentBatch: golongan PTKP pegawai (Pph21Golongan::
 * fromStatus) + gaji kotor bulan berjalan DIGABUNG dengan lumpsum SPPD
 * untuk MENCARI tarif TER yang berlaku, tapi tarif itu HANYA diterapkan
 * ke lumpsum SPPD (gaji kotornya sendiri sudah/akan dipajaki terpisah
 * lewat payroll bulanan, modul ini tidak menyentuhnya — mencegah pajak
 * dobel atas komponen yang sama).
 */
final class ProcessSppdPaymentBatch
{
    public function __construct(
        private readonly AuditRepository $audit,
        private readonly SalaryScaleRepository $salaryScale,
        private readonly TerRateRepository $terRates,
    ) {}

    /**
     * @param  array<int, string>  $requestIds
     * @return string id batch yang terbentuk
     */
    public function handle(
        array $requestIds,
        string $memoGroupId,
        string $signatoryEmployeeId,
        string $expenseAccountId,
        string $taxExpenseAccountId,
        string $taxAccountId,
        string $payerScope,
        ?string $officeId,
        AuditActor $actor,
    ): string {
        if ($requestIds === []) {
            throw new DomainException('Pilih minimal satu pegawai untuk dibayar.');
        }

        return DB::transaction(function () use ($requestIds, $memoGroupId, $signatoryEmployeeId, $expenseAccountId, $taxExpenseAccountId, $taxAccountId, $payerScope, $officeId, $actor) {
            $now = new DateTimeImmutable;
            $asOf = AsOfDate::on($now);

            $items = [];
            $totalGross = 0;
            $totalTax = 0;
            $totalNet = 0;

            foreach ($requestIds as $requestId) {
                $request = DB::table('spd_requests as r')
                    ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
                    ->where('r.id', $requestId)
                    ->select(
                        'r.id', 'r.status', 'r.memo_group_id',
                        'r.uang_makan_cents', 'r.uang_saku_cents', 'r.estimasi_hotel_cents', 'r.hotel_kompensasi_cents',
                        'r.estimasi_angkutan_setempat_cents', 'r.estimasi_transportasi_tujuan_cents',
                        'r.uang_makan_h1_cents', 'r.uang_saku_h1_cents', 'r.uang_makan_konsumsi_cents',
                        'e.id as employee_id', 'e.full_name', 'e.nomor_simpeda',
                        'e.person_grade', 'e.salary_step', 'e.tunjangan_jabatan_cents', 'e.tunjangan_penyesuaian_cents',
                        'e.marital_status', 'e.tanggungan',
                    )
                    ->lockForUpdate()
                    ->first();

                if ($request === null) {
                    throw new DomainException('Salah satu pengajuan SPPD tidak ditemukan.');
                }

                if ($request->status !== 'approved') {
                    throw new DomainException('Hanya pengajuan SPPD berstatus approved yang dapat dibayarkan.');
                }

                if ($request->memo_group_id !== $memoGroupId) {
                    throw new DomainException('Salah satu pengajuan SPPD bukan bagian dari grup memo yang dipilih.');
                }

                $alreadyPaid = DB::table('spd_payment_batch_items')->where('spd_request_id', $requestId)->exists();

                if ($alreadyPaid) {
                    throw new DomainException('Salah satu pengajuan SPPD sudah pernah dibayarkan pada batch lain.');
                }

                if ($request->marital_status === null) {
                    throw new DomainException("Status PTKP (status kawin) {$request->full_name} belum diisi — lengkapi data pegawai sebelum bisa dihitung pajaknya.");
                }

                if ($request->person_grade === null) {
                    throw new DomainException("{$request->full_name} belum punya person grade — gaji kotor tidak bisa dihitung untuk basis tarif TER.");
                }

                $grossCents = (int) $request->uang_makan_cents
                    + (int) $request->uang_saku_cents
                    + (int) ($request->estimasi_hotel_cents ?? 0)
                    + (int) ($request->hotel_kompensasi_cents ?? 0)
                    + (int) ($request->estimasi_angkutan_setempat_cents ?? 0)
                    + (int) ($request->estimasi_transportasi_tujuan_cents ?? 0)
                    + (int) ($request->uang_makan_h1_cents ?? 0)
                    + (int) ($request->uang_saku_h1_cents ?? 0)
                    + (int) ($request->uang_makan_konsumsi_cents ?? 0);

                $grossSppd = Money::fromCents($grossCents);

                try {
                    $gajiKotor = $this->gajiKotorFor(
                        (int) $request->person_grade,
                        (int) $request->salary_step,
                        (int) $request->tunjangan_jabatan_cents,
                        (int) $request->tunjangan_penyesuaian_cents,
                        $asOf,
                    );
                } catch (SalaryStepNotFound $e) {
                    throw new DomainException("Skala gaji {$request->full_name}: {$e->getMessage()}");
                }

                $golongan = Pph21Golongan::fromStatus($request->marital_status === 'menikah', (int) $request->tanggungan);
                $combinedIncome = $gajiKotor->add($grossSppd);

                try {
                    $ratePercent = $this->terRates->ratePercentFor($golongan, $combinedIncome->cents, $asOf);
                } catch (TerRateNotFound $e) {
                    throw new DomainException("Tarif TER untuk {$request->full_name}: {$e->getMessage()}");
                }

                // Tarif dicari dari PENGHASILAN GABUNGAN (langkah di atas),
                // tapi diterapkan HANYA ke lumpsum SPPD — bukan ke gabungan —
                // supaya gaji kotor yang sudah/akan dipajaki lewat payroll
                // bulanan tidak terpotong pajak dua kali di sini (pola SAMA
                // ProcessBekalCutiPaymentBatch).
                $tax = $grossSppd->percentage($ratePercent);
                $net = $grossSppd->subtract($tax);

                $items[] = [
                    'spd_request_id' => $request->id,
                    'employee_id' => $request->employee_id,
                    'amount_cents' => $grossCents,
                    'gaji_kotor_cents' => $gajiKotor->cents,
                    'combined_income_cents' => $combinedIncome->cents,
                    'pph21_golongan' => $golongan->value,
                    'tax_rate_percent' => $ratePercent,
                    'tax_cents' => $tax->cents,
                    'net_cents' => $net->cents,
                    'bank_account_number' => $request->nomor_simpeda,
                ];

                $totalGross += $grossCents;
                $totalTax += $tax->cents;
                $totalNet += $net->cents;
            }

            $batchId = (string) Uuid7::generate();
            $batchNumber = $this->nextBatchNumber($now);

            DB::table('spd_payment_batches')->insert([
                'id' => $batchId,
                'batch_number' => $batchNumber,
                'memo_group_id' => $memoGroupId,
                'payer_scope' => $payerScope,
                'office_id' => $officeId,
                'signatory_employee_id' => $signatoryEmployeeId,
                'journal_expense_account_id' => $expenseAccountId,
                'journal_tax_expense_account_id' => $taxExpenseAccountId,
                'journal_tax_account_id' => $taxAccountId,
                'total_amount_cents' => $totalGross,
                'total_tax_cents' => $totalTax,
                'total_net_cents' => $totalNet,
                'created_by' => $actor->actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            foreach ($items as $item) {
                DB::table('spd_payment_batch_items')->insert([
                    'id' => (string) Uuid7::generate(),
                    'batch_id' => $batchId,
                    ...$item,
                    'created_at' => $now,
                ]);

                DB::table('spd_requests')->where('id', $item['spd_request_id'])->update([
                    'status' => 'disbursed',
                    'disbursed_by' => $actor->actorId,
                    'disbursed_at' => $now,
                    'disbursement_reference' => $batchNumber,
                    'updated_at' => $now,
                ]);

                $this->audit->append(new AuditEntry(
                    occurredAt: $now,
                    actor: $actor,
                    auditableType: 'spd_request',
                    auditableId: $item['spd_request_id'],
                    action: AuditAction::Disbursed,
                    contextRef: $batchNumber,
                ));
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'spd_payment_batch',
                auditableId: $batchId,
                action: AuditAction::Created,
                newValues: [
                    'jumlah_pegawai' => count($items),
                    'total_amount_cents' => $totalGross,
                    'total_tax_cents' => $totalTax,
                    'total_net_cents' => $totalNet,
                ],
            ));

            return $batchId;
        });
    }

    private function gajiKotorFor(int $personGrade, int $salaryStep, int $tunjanganJabatanCents, int $tunjanganPenyesuaianCents, AsOfDate $asOf): Money
    {
        $imbalanKerja = $this->salaryScale->amountFor($personGrade, $salaryStep, $asOf);

        return $imbalanKerja
            ->add(Money::fromCents($tunjanganJabatanCents))
            ->add(Money::fromCents($tunjanganPenyesuaianCents));
    }

    private function nextBatchNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('SPPD-BAYAR/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('spd_payment_batches')->where('batch_number', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
