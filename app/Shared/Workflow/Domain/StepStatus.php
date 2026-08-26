<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

enum StepStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Expired = 'expired';
    case Escalated = 'escalated';

    public function isTerminal(): bool
    {
        return $this !== self::Pending && $this !== self::Escalated;
    }

    /**
     * Status yang masih MEMESAN kuota pegawai (DEC-32).
     * Pengajuan lembur yang menunggu keputusan tetap menahan jatah
     * 18 jam/minggu — sehingga pelepasan kuota harus andal.
     */
    public function stillReservesQuota(): bool
    {
        return $this === self::Pending || $this === self::Escalated;
    }
}
