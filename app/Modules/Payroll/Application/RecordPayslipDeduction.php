<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Payroll\Domain\DeductionType;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Input potongan MANUAL satu-satu oleh admin cabang (maker) — hanya
 * selama payroll run induknya berstatus draft (§ ditegakkan di sini,
 * bukan hanya lewat kepemilikan kantor yang sudah digerbangi di
 * Interfaces — pertahanan berlapis terhadap kondisi balapan run
 * diputus SAAT form sedang diisi).
 */
final class RecordPayslipDeduction
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $payslipId, DeductionType $type, int $amountCents, ?string $note, AuditActor $actor): string
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Jumlah potongan harus lebih dari nol.');
        }

        return DB::transaction(function () use ($payslipId, $type, $amountCents, $note, $actor) {
            $payslip = DB::table('pay_payslips as s')
                ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
                ->where('s.id', $payslipId)
                ->select('s.id', 'r.status')
                ->lockForUpdate()
                ->first();

            if ($payslip === null) {
                throw new DomainException('Slip gaji tidak ditemukan.');
            }

            if ($payslip->status !== 'draft') {
                throw new DomainException('Payroll run sudah tidak berstatus draft — potongan tidak dapat diubah.');
            }

            $id = (string) Uuid7::generate();
            $now = new DateTimeImmutable;

            DB::table('pay_payslip_deductions')->insert([
                'id' => $id,
                'payslip_id' => $payslipId,
                'deduction_type' => $type->value,
                'amount_cents' => $amountCents,
                'note' => $note,
                'created_by' => $actor->actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'payslip_deduction',
                auditableId: $id,
                action: AuditAction::Created,
                newValues: ['payslip_id' => $payslipId, 'deduction_type' => $type->value, 'amount_cents' => $amountCents],
            ));

            return $id;
        });
    }
}
