<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RemovePayslipDeduction
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $deductionId, AuditActor $actor): void
    {
        DB::transaction(function () use ($deductionId, $actor) {
            $deduction = DB::table('pay_payslip_deductions as d')
                ->join('pay_payslips as s', 's.id', '=', 'd.payslip_id')
                ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
                ->where('d.id', $deductionId)
                ->select('d.id', 'd.payslip_id', 'd.deduction_type', 'd.amount_cents', 'r.status')
                ->lockForUpdate()
                ->first();

            if ($deduction === null) {
                throw new DomainException('Baris potongan tidak ditemukan.');
            }

            if ($deduction->status !== 'draft') {
                throw new DomainException('Payroll run sudah tidak berstatus draft — potongan tidak dapat diubah.');
            }

            DB::table('pay_payslip_deductions')->where('id', $deductionId)->delete();

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'payslip_deduction',
                auditableId: $deductionId,
                action: AuditAction::Deleted,
                oldValues: [
                    'payslip_id' => $deduction->payslip_id,
                    'deduction_type' => $deduction->deduction_type,
                    'amount_cents' => $deduction->amount_cents,
                ],
            ));
        });
    }
}
