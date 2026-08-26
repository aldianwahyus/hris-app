<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use App\Shared\Workflow\Domain\Strategy\ApprovalStrategy;
use App\Shared\Workflow\Domain\Strategy\CommitteeStrategy;
use App\Shared\Workflow\Domain\Strategy\SingleApproverStrategy;
use DomainException;

/**
 * Satu langkah dalam alur, memuat pola persetujuan dan ketentuan SLA.
 *
 * Strategi dipilih dari pola — bukan percabangan kondisi tersebar di
 * berbagai tempat. Penambahan pola baru cukup menambah kelas strategi.
 */
final readonly class WorkflowStep
{
    public function __construct(
        public string $id,
        public int $sequence,
        public string $name,
        public ApprovalPattern $pattern,
        public ?int $quorum = null,
        public bool $requireUnanimous = false,
        public ?int $slaDays = null,
        /** @var array<int,int> */
        public array $reminderDaysBefore = [7, 3],
    ) {
        if ($pattern->requiresQuorum() && ($quorum === null || $quorum < 1)) {
            throw new DomainException(sprintf(
                'Langkah "%s" berpola komite sehingga wajib menetapkan kuorum.',
                $name
            ));
        }

        if ($pattern->requiresSlaMonitoring() && $slaDays === null) {
            throw new DomainException(sprintf(
                'Langkah "%s" berpola terpusat sehingga SLA wajib ditetapkan — '
                . 'keterlambatan persetujuan berdampak pada hak bayar pegawai.',
                $name
            ));
        }
    }

    public function strategy(): ApprovalStrategy
    {
        return match ($this->pattern) {
            ApprovalPattern::IndividualHierarchical,
            ApprovalPattern::CentralizedSingle => new SingleApproverStrategy(),
            ApprovalPattern::Committee => new CommitteeStrategy(
                quorum: $this->quorum,
                requireUnanimous: $this->requireUnanimous,
            ),
        };
    }
}
