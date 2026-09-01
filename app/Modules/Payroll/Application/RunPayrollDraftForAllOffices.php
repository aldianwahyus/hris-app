<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

use App\Modules\Payroll\Domain\PayrollRunAlreadyExists;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * hr_approver (BANK_WIDE) men-generate draf payroll SEMUA kantor
 * sekaligus untuk satu periode — melengkapi RunPayrollDraft::handle()
 * per-kantor milik hr_admin (OFFICE-scoped), bukan menggantikannya.
 *
 * REUSE penuh RunPayrollDraft::handle() per kantor — logika
 * perhitungan Imbalan Kerja/iuran TIDAK diduplikasi di sini, kelas
 * ini murni mengorkestrasi iterasi + menahan kegagalan SATU kantor
 * (mis. sudah pernah di-generate) agar tidak menggagalkan kantor lain.
 */
final class RunPayrollDraftForAllOffices
{
    public function __construct(private readonly RunPayrollDraft $runDraft) {}

    public function handle(DateTimeImmutable $period, AuditActor $actor): BulkPayrollDraftResult
    {
        // Hanya kantor dengan minimal SATU pegawai eligible (kriteria
        // SAMA seperti RunPayrollDraft: tetap + person_grade terisi) —
        // kantor tanpa pegawai eligible dilewati TANPA membuat draf run
        // kosong, beda dari perilaku RunPayrollDraft per-kantor yang
        // tetap membuat run meski 0 slip bila dipanggil manual.
        $eligibleOffices = DB::table('emp_employees')
            ->where('employment_status', 'tetap')
            ->whereNotNull('person_grade')
            ->distinct()
            ->pluck('office_id');

        $offices = DB::table('md_offices')
            ->whereIn('id', $eligibleOffices)
            ->orderBy('name')
            ->get(['id', 'name']);

        $created = [];
        $skipped = [];
        $failedEmployees = [];

        foreach ($offices as $office) {
            try {
                $officeFailures = [];
                $this->runDraft->handle(officeId: $office->id, period: $period, actor: $actor, failedEmployees: $officeFailures);
                $created[] = $office->name;

                foreach ($officeFailures as $failure) {
                    $failedEmployees[] = ['office' => $office->name, 'name' => $failure['name'], 'reason' => $failure['reason']];
                }
            } catch (PayrollRunAlreadyExists) {
                $skipped[] = $office->name;
            }
        }

        return new BulkPayrollDraftResult(createdOfficeNames: $created, skippedAlreadyExistsOfficeNames: $skipped, failedEmployees: $failedEmployees);
    }
}
