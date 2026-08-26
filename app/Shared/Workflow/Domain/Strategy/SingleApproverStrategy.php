<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain\Strategy;

use App\Shared\Workflow\Domain\StepStatus;

/**
 * Satu keputusan menentukan hasil.
 *
 * Dipakai oleh pola Individual Berjenjang (Cuti) maupun Tunggal
 * Terpusat (Lembur). Perbedaan keduanya bukan pada cara memutuskan,
 * melainkan pada cara MENENTUKAN SIAPA approver-nya — itu urusan
 * ApproverResolver, bukan strategi ini.
 */
final class SingleApproverStrategy implements ApprovalStrategy
{
    public function resolve(array $decisions): StepStatus
    {
        if ($decisions === []) {
            return StepStatus::Pending;
        }

        $decision = $decisions[array_key_first($decisions)];

        return $decision->approved ? StepStatus::Approved : StepStatus::Rejected;
    }

    public function needsMoreDecisions(array $decisions): bool
    {
        return $decisions === [];
    }
}
