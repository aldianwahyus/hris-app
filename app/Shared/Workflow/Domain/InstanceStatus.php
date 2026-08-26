<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

enum InstanceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * Status yang membebaskan kembali kuota pegawai.
     * Penting untuk plafon lembur mingguan: pengajuan yang ditolak,
     * dibatalkan, atau kedaluwarsa harus melepaskan jam yang sempat
     * dipesan, agar pegawai tidak terkunci oleh permohonan lama.
     */
    public function releasesQuota(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled, self::Expired], true);
    }
}
