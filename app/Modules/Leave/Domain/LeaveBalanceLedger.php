<?php

declare(strict_types=1);

namespace App\Modules\Leave\Domain;

/**
 * Menyusun rencana konsumsi kantong saldo cuti sesuai urutan
 * (consumption_order) — jatah tahun berjalan habis dulu, baru sisa
 * tahun lalu. Kelas ini hanya MEMUTUSKAN; penerapan pengurangan ke
 * basis data adalah tanggung jawab Application/Infrastructure.
 */
final readonly class LeaveBalanceLedger
{
    /** Toleransi pembulatan — quota/used disimpan dengan 1 desimal. */
    private const EPSILON = 0.001;

    /** @param array<int, LeaveBucket> $buckets */
    public function __construct(private array $buckets) {}

    /** @return array<int, BucketDebit> */
    public function planConsumption(float $requestedDays): array
    {
        $sorted = $this->buckets;
        usort($sorted, fn (LeaveBucket $a, LeaveBucket $b) => $a->consumptionOrder <=> $b->consumptionOrder);

        $remaining = $requestedDays;
        $plan = [];

        foreach ($sorted as $bucket) {
            if ($remaining <= self::EPSILON) {
                break;
            }

            $take = min($bucket->remaining(), $remaining);

            if ($take > self::EPSILON) {
                $plan[] = new BucketDebit($bucket->type, $take);
                $remaining -= $take;
            }
        }

        if ($remaining > self::EPSILON) {
            throw InsufficientLeaveBalance::forShortfall($remaining);
        }

        return $plan;
    }
}
