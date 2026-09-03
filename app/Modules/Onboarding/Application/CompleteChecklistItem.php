<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mencentang/membatalkan centang satu item checklist onboarding.
 * `onb_employee_checklists.completed_at` diperbarui otomatis:
 * terisi begitu SELURUH item selesai, dikosongkan lagi bila ada item
 * yang dibatalkan centangnya setelah checklist sempat lengkap.
 */
final class CompleteChecklistItem
{
    public function handle(string $itemId, bool $isDone, string $actorEmployeeId, ?string $notes): void
    {
        DB::transaction(function () use ($itemId, $isDone, $actorEmployeeId, $notes) {
            $item = DB::table('onb_employee_checklist_items')->where('id', $itemId)->lockForUpdate()->first();

            if ($item === null) {
                throw new DomainException('Item checklist tidak ditemukan.');
            }

            $now = new DateTimeImmutable;

            DB::table('onb_employee_checklist_items')->where('id', $itemId)->update([
                'is_done' => $isDone,
                'done_by' => $isDone ? $actorEmployeeId : null,
                'done_at' => $isDone ? $now : null,
                'notes' => $notes,
            ]);

            $remaining = DB::table('onb_employee_checklist_items')
                ->where('checklist_id', $item->checklist_id)
                ->where('is_done', false)
                ->count();

            DB::table('onb_employee_checklists')->where('id', $item->checklist_id)->update([
                'completed_at' => $remaining === 0 ? $now : null,
            ]);
        });
    }
}
