<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran Lembur MASSAL — menggantikan alur satu-per-satu lama
 * (DisburseOvertimePayment, dipertahankan untuk kompatibilitas riwayat,
 * TIDAK dipanggil lagi dari controller). Satu langkah: hitung
 * gross/pajak/net DAN commit sekaligus (pola sama DisburseOvertimePayment
 * yang juga langsung commit, bukan preview terpisah).
 *
 * Tarif pajak (OVT_TAX_RATE_PERCENT) SNAPSHOT ke `ovt_payment_batches.
 * tax_rate_percent` saat batch dibuat — parameter bisa berubah nanti
 * lewat Konfigurasi Parameter, tapi batch yang SUDAH dibuat harus tetap
 * mencerminkan tarif yang berlaku SAAT itu (bukti audit), bukan
 * ikut berubah kalau tarif diubah kemudian.
 *
 * Pemisahan tugas (§6.3): pejabat pengusul (signatory) TIDAK BOLEH
 * sama dengan approver tahap-2 (Pimpinan Kantor) dari SALAH SATU
 * pengajuan yang dibayar — pola sama guard swa-cair
 * DisburseOvertimePayment, diterapkan per-baris di sini karena satu
 * batch bisa mencakup banyak pengajuan dengan approver berbeda-beda.
 */
final class ProcessOvertimePaymentBatch
{
    public function __construct(
        private readonly AuditRepository $audit,
        private readonly ParameterResolver $parameters,
    ) {}

    /**
     * @param  array<int, string>  $requestIds
     * @return string id batch yang terbentuk
     */
    public function handle(
        array $requestIds,
        string $signatoryEmployeeId,
        string $expenseAccountId,
        string $taxAccountId,
        string $payerScope,
        ?string $officeId,
        ?string $division,
        AuditActor $actor,
    ): string {
        if ($requestIds === []) {
            throw new DomainException('Pilih minimal satu pengajuan lembur untuk dibayar.');
        }

        return DB::transaction(function () use ($requestIds, $signatoryEmployeeId, $expenseAccountId, $taxAccountId, $payerScope, $officeId, $division, $actor) {
            $now = new DateTimeImmutable;
            $taxRatePercent = $this->parameters->decimal('OVT_TAX_RATE_PERCENT', AsOfDate::on($now));

            $items = [];
            $totalGross = 0;
            $totalTax = 0;
            $totalNet = 0;

            foreach ($requestIds as $requestId) {
                // Tidak perlu guard "SPKL lain, pegawai+tanggal sama, sudah
                // disbursed" di sini — index unik parsial
                // ovt_requests_employee_workdate_unique (migrasi
                // 2026_09_05_000001) sudah membuat MUSTAHIL dua baris
                // ovt_requests hidup (bukan rejected/expired, termasuk
                // disbursed) ada sekaligus untuk employee_id+work_date yang
                // sama — dicegah sejak SubmitOvertimeRequest, bukan
                // ditambal di sini.
                $request = DB::table('ovt_requests as r')
                    ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
                    ->where('r.id', $requestId)
                    ->select('r.id', 'r.status', 'r.approver_id', 'r.amount_cents', 'e.id as employee_id', 'e.nomor_simpeda')
                    ->lockForUpdate()
                    ->first();

                if ($request === null) {
                    throw new DomainException('Salah satu pengajuan lembur tidak ditemukan.');
                }

                if ($request->status !== 'approved') {
                    throw new DomainException('Hanya pengajuan lembur berstatus approved yang dapat dibayarkan.');
                }

                if ($request->approver_id === $signatoryEmployeeId) {
                    throw new DomainException('Pejabat pengusul tidak boleh sama dengan penyetuju tahap-2 salah satu pengajuan yang dibayar.');
                }

                // Bug ditemukan lewat audit kode: pengecekan di atas hanya
                // menjaga NAMA PEJABAT PENGUSUL di dokumen cetak, TIDAK
                // PERNAH memeriksa aktor yang benar-benar login dan
                // memproses batch ini. DisburseOvertimePayment (pola yang
                // diklaim ditiru kelas ini, lihat docblock) justru
                // membandingkan approver_id dengan $actor->actorId —
                // tanpa baris ini, pejabat yang menyetujui lembur (tahap-2)
                // bisa login sebagai hr_admin/hr_approver dan memproses
                // sendiri pembayarannya, cukup mencantumkan nama kolega
                // lain sebagai pejabat pengusul di kertas (§6.3).
                if ($request->approver_id === $actor->actorId) {
                    throw new DomainException('Anda tidak dapat memproses pembayaran untuk pengajuan yang Anda setujui sendiri pada tahap Pimpinan Kantor (§6.3).');
                }

                $alreadyPaid = DB::table('ovt_payment_batch_items')->where('ovt_request_id', $requestId)->exists();

                if ($alreadyPaid) {
                    throw new DomainException('Salah satu pengajuan lembur sudah pernah dibayarkan pada batch lain.');
                }

                $grossCents = (int) $request->amount_cents;
                $taxCents = (int) round($grossCents * $taxRatePercent / 100);
                $netCents = $grossCents - $taxCents;

                $items[] = [
                    'ovt_request_id' => $request->id,
                    'employee_id' => $request->employee_id,
                    'gross_cents' => $grossCents,
                    'tax_cents' => $taxCents,
                    'net_cents' => $netCents,
                    'bank_account_number' => $request->nomor_simpeda,
                ];

                $totalGross += $grossCents;
                $totalTax += $taxCents;
                $totalNet += $netCents;
            }

            $batchId = (string) Uuid7::generate();
            $spklNumber = $this->nextBatchSpklNumber($now);

            DB::table('ovt_payment_batches')->insert([
                'id' => $batchId,
                'spkl_number' => $spklNumber,
                'payer_scope' => $payerScope,
                'office_id' => $officeId,
                'division' => $division,
                'signatory_employee_id' => $signatoryEmployeeId,
                'journal_expense_account_id' => $expenseAccountId,
                'journal_tax_account_id' => $taxAccountId,
                'tax_rate_percent' => $taxRatePercent,
                'total_gross_cents' => $totalGross,
                'total_tax_cents' => $totalTax,
                'total_net_cents' => $totalNet,
                'created_by' => $actor->actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            foreach ($items as $item) {
                DB::table('ovt_payment_batch_items')->insert([
                    'id' => (string) Uuid7::generate(),
                    'batch_id' => $batchId,
                    ...$item,
                    'created_at' => $now,
                ]);

                DB::table('ovt_requests')->where('id', $item['ovt_request_id'])->update([
                    'status' => 'disbursed',
                    'disbursed_by' => $actor->actorId,
                    'disbursed_at' => $now,
                    'disbursement_reference' => $spklNumber,
                    'updated_at' => $now,
                ]);

                $this->audit->append(new AuditEntry(
                    occurredAt: $now,
                    actor: $actor,
                    auditableType: 'ovt_request',
                    auditableId: $item['ovt_request_id'],
                    action: AuditAction::Disbursed,
                    contextRef: $spklNumber,
                ));
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'ovt_payment_batch',
                auditableId: $batchId,
                action: AuditAction::Created,
                newValues: [
                    'jumlah_pengajuan' => count($items),
                    'total_gross_cents' => $totalGross,
                    'total_tax_cents' => $totalTax,
                    'total_net_cents' => $totalNet,
                ],
            ));

            return $batchId;
        });
    }

    private function nextBatchSpklNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('SPKL-BAYAR/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('ovt_payment_batches')
            ->where('spkl_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
