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

/**
 * Pejabat SDM (checker, lingkup BANK_WIDE) menyetujui/menolak draf
 * payroll — checker TIDAK BOLEH menjadi maker atas run yang sama
 * (§6.3), ditegakkan di sini, bukan hanya di lapisan Interfaces.
 */
final class DecidePayrollRun
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function approve(string $runId, AuditActor $actor): void
    {
        $this->decide($runId, 'approved', AuditAction::Approved, $actor);
    }

    public function reject(string $runId, AuditActor $actor): void
    {
        $this->decide($runId, 'rejected', AuditAction::Rejected, $actor);
    }

    /**
     * Menutup periode input potongan untuk SELURUH kantor sekaligus —
     * meng-approve semua payroll run berstatus draft pada satu periode.
     * BEDA dari approve() satu-run: di sini larangan self-approval
     * SENGAJA tidak ditegakkan (enforceSelfCheck: false ke decide()) —
     * keputusan eksplisit pengguna bahwa aksi tutup-periode bank-wide ini
     * boleh dilakukan orang yang sama dengan yang men-generate
     * (RunPayrollDraftForAllOffices). approve()/reject() satu-run TIDAK
     * berubah, tetap melarang seperti sebelumnya.
     */
    public function approveAllForPeriod(DateTimeImmutable $period, AuditActor $actor): BulkPayrollDecisionResult
    {
        $periodDate = $period->format('Y-m-d');

        $draftRuns = DB::table('pay_payroll_runs as r')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->where('r.period', $periodDate)
            ->where('r.status', 'draft')
            ->get(['r.id', 'o.name as office_name']);

        $approved = [];

        foreach ($draftRuns as $run) {
            $this->decide($run->id, 'approved', AuditAction::Approved, $actor, enforceSelfCheck: false);
            $approved[] = $run->office_name;
        }

        return new BulkPayrollDecisionResult(approvedOfficeNames: $approved, period: $periodDate);
    }

    /**
     * Membuka kembali payroll run yang SUDAH approved supaya admin
     * cabang bisa mengubah potongan lagi (lihat PayrollDeductionController
     * — akses admin cabang digerbangi status='draft', jadi reopen ini
     * SATU-SATUNYA cara mengembalikan akses itu). Hanya dari status
     * approved — run draft belum pernah dikunci, rejected tidak relevan
     * dibuka lagi (tidak ada potongan yang perlu diubah pada run yang
     * ditolak sejak awal).
     */
    public function reopen(string $runId, AuditActor $actor): void
    {
        DB::transaction(function () use ($runId, $actor) {
            $run = DB::table('pay_payroll_runs')->where('id', $runId)->lockForUpdate()->first();

            if ($run === null) {
                throw new DomainException('Payroll run tidak ditemukan.');
            }

            if ($run->status !== 'approved') {
                throw new DomainException('Hanya payroll run berstatus approved yang dapat dibuka kembali.');
            }

            DB::table('pay_payroll_runs')->where('id', $runId)->update([
                'status' => 'draft',
                'updated_at' => new DateTimeImmutable,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'payroll_run',
                auditableId: $runId,
                action: AuditAction::Reopened,
                contextRef: $run->period,
            ));
        });
    }

    private function decide(string $runId, string $status, AuditAction $action, AuditActor $actor, bool $enforceSelfCheck = true): void
    {
        DB::transaction(function () use ($runId, $status, $action, $actor, $enforceSelfCheck) {
            $run = DB::table('pay_payroll_runs')->where('id', $runId)->lockForUpdate()->first();

            if ($run === null) {
                throw new DomainException('Payroll run tidak ditemukan.');
            }

            if ($run->status !== 'draft') {
                throw new DomainException('Payroll run ini sudah diputus sebelumnya.');
            }

            if ($enforceSelfCheck && $run->created_by === $actor->actorId) {
                throw new DomainException('Tidak dapat memutus payroll run yang Anda buat sendiri.');
            }

            $now = new DateTimeImmutable;

            DB::table('pay_payroll_runs')->where('id', $runId)->update([
                'status' => $status,
                'approved_by' => $actor->actorId,
                'decided_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'payroll_run',
                auditableId: $runId,
                action: $action,
                contextRef: $run->period,
            ));
        });
    }
}
