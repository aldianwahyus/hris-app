<?php

declare(strict_types=1);

namespace App\Modules\Lms\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Gamifikasi (BRD §5.8) — poin ledger append-only (pola sama
 * aud_change_logs, histori bukan kolom total ditimpa). Dipanggil dari
 * peristiwa NYATA yang sudah ada (kelulusan kursus, kelulusan asesmen,
 * penyelesaian challenge) — BUKAN aksi manual terpisah untuk poin itu
 * sendiri.
 */
final class AwardGamificationPoints
{
    public function handle(string $employeeId, int $points, string $reason, ?string $sourceType = null, ?string $sourceId = null): void
    {
        DB::table('lms_gamification_points')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'points' => $points,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_at' => new DateTimeImmutable,
        ]);
    }
}
