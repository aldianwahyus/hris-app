<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Membangkitkan checklist onboarding untuk SATU pegawai baru dari
 * template yang cocok — dipicu dari
 * EmployeeApprovalQueueController::approveNewEmployee() PERSIS
 * seperti triggerBekalCutiIfFirstThisYear (pemicu di lapisan
 * Interfaces, BUKAN di dalam transaksi DecideNewEmployeeRequest,
 * supaya modul Employee tidak perlu mengenal modul Onboarding —
 * ModuleBoundaryTest M-1 melarang impor Application antar modul
 * kecuali lewat Contracts/).
 *
 * Item checklist DISALIN (snapshot item_name/category), BUKAN
 * mereferensikan template_item_id — perubahan template di kemudian
 * hari tidak boleh diam-diam mengubah checklist yang sudah berjalan.
 *
 * Tidak melempar galat bila belum ada template yang cocok (HC belum
 * sempat menyusun template) — cukup tidak melakukan apa pun, supaya
 * proses persetujuan pegawai baru TIDAK terhambat oleh modul ini.
 */
final class GenerateOnboardingChecklist
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $employeeId, string $employmentStatus, ?AuditActor $actor = null): ?string
    {
        return DB::transaction(function () use ($employeeId, $employmentStatus, $actor) {
            $existing = DB::table('onb_employee_checklists')->where('employee_id', $employeeId)->value('id');

            if ($existing !== null) {
                return $existing;
            }

            $template = DB::table('onb_checklist_templates')
                ->where('is_active', true)
                ->where('employment_status_scope', $employmentStatus)
                ->first()
                ?? DB::table('onb_checklist_templates')
                    ->where('is_active', true)
                    ->whereNull('employment_status_scope')
                    ->first();

            if ($template === null) {
                return null;
            }

            $items = DB::table('onb_checklist_template_items')->where('template_id', $template->id)->orderBy('display_order')->get();

            if ($items->isEmpty()) {
                return null;
            }

            $now = new DateTimeImmutable;
            $checklistId = (string) Uuid7::generate();

            DB::table('onb_employee_checklists')->insert([
                'id' => $checklistId,
                'employee_id' => $employeeId,
                'template_id' => $template->id,
                'started_at' => $now,
                'completed_at' => null,
            ]);

            foreach ($items as $item) {
                DB::table('onb_employee_checklist_items')->insert([
                    'id' => (string) Uuid7::generate(),
                    'checklist_id' => $checklistId,
                    'item_name' => $item->item_name,
                    'category' => $item->category,
                    'is_done' => false,
                    'done_by' => null,
                    'done_at' => null,
                    'notes' => null,
                ]);
            }

            if ($actor !== null) {
                $this->audit->append(new AuditEntry(
                    occurredAt: $now,
                    actor: $actor,
                    auditableType: 'onb_employee_checklist',
                    auditableId: $checklistId,
                    action: AuditAction::Created,
                    newValues: ['employee_id' => $employeeId, 'template_id' => $template->id, 'item_count' => $items->count()],
                ));
            }

            return $checklistId;
        });
    }
}
