<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain\Strategy;

use App\Shared\Workflow\Domain\StepStatus;
use InvalidArgumentException;

/**
 * Keputusan kolektif dengan syarat kuorum — dipakai Komite Pertimbangan
 * Pelanggaran (KPP) dan Komite Human Capital (KHC).
 *
 * Berbeda dari pola lain: satu suara TIDAK menentukan hasil. Langkah
 * baru selesai setelah jumlah suara memenuhi kuorum.
 *
 * requireUnanimous=true berarti seluruh anggota harus setuju; satu
 * penolakan langsung menggugurkan.
 */
final class CommitteeStrategy implements ApprovalStrategy
{
    public function __construct(
        private readonly int $quorum,
        private readonly bool $requireUnanimous = false,
    ) {
        if ($quorum < 1) {
            throw new InvalidArgumentException('Kuorum minimal 1 anggota.');
        }
    }

    public function resolve(array $decisions): StepStatus
    {
        $approvals = 0;
        $rejections = 0;

        foreach ($decisions as $decision) {
            $decision->approved ? $approvals++ : $rejections++;
        }

        if ($this->requireUnanimous && $rejections > 0) {
            return StepStatus::Rejected;
        }

        if (count($decisions) < $this->quorum) {
            return StepStatus::Pending;
        }

        // Kuorum tercapai: suara terbanyak menentukan.
        // Seri diperlakukan sebagai penolakan — pada konteks sanksi
        // pegawai, ketiadaan mayoritas yang mendukung berarti usulan
        // tidak dapat dilanjutkan.
        return $approvals > $rejections ? StepStatus::Approved : StepStatus::Rejected;
    }

    public function needsMoreDecisions(array $decisions): bool
    {
        if ($this->requireUnanimous) {
            foreach ($decisions as $decision) {
                if (! $decision->approved) {
                    return false;
                }
            }
        }

        return count($decisions) < $this->quorum;
    }
}
