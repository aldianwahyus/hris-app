<?php

declare(strict_types=1);

namespace App\Modules\Offboarding\Application;

use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Mencentang/membatalkan centang satu item clearance offboarding. */
final class CompleteClearanceItem
{
    public function handle(string $itemId, bool $isDone, string $actorEmployeeId): void
    {
        $item = DB::table('off_clearance_items')->where('id', $itemId)->first();

        if ($item === null) {
            throw new DomainException('Item clearance tidak ditemukan.');
        }

        DB::table('off_clearance_items')->where('id', $itemId)->update([
            'is_done' => $isDone,
            'done_by' => $isDone ? $actorEmployeeId : null,
            'done_at' => $isDone ? new DateTimeImmutable : null,
        ]);
    }
}
