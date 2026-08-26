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

final class RemovePayslipAddition
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $additionId, AuditActor $actor): void
    {
        DB::transaction(function () use ($additionId, $actor) {
            $addition = DB::table('pay_payslip_additions as a')
                ->join('pay_payslips as s', 's.id', '=', 'a.payslip_id')
                ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
                ->where('a.id', $additionId)
                ->select('a.id', 'a.payslip_id', 'a.addition_type', 'a.amount_cents', 'r.status')
                ->lockForUpdate()
                ->first();

            if ($addition === null) {
                throw new DomainException('Baris tambahan penghasilan tidak ditemukan.');
            }

            if ($addition->status !== 'draft') {
                throw new DomainException('Payroll run sudah tidak berstatus draft — tambahan penghasilan tidak dapat diubah.');
            }

            DB::table('pay_payslip_additions')->where('id', $additionId)->delete();

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'payslip_addition',
                auditableId: $additionId,
                action: AuditAction::Deleted,
                oldValues: [
                    'payslip_id' => $addition->payslip_id,
                    'addition_type' => $addition->addition_type,
                    'amount_cents' => $addition->amount_cents,
                ],
            ));
        });
    }
}
