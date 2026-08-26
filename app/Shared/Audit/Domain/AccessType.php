<?php

declare(strict_types=1);

namespace App\Shared\Audit\Domain;

enum AccessType: string
{
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Printed = 'printed';
    case Exported = 'exported';

    /**
     * Akses yang memindahkan data keluar sistem — risiko kebocoran
     * tertinggi menurut UU PDP, sehingga perlu perhatian khusus
     * pada laporan pengawasan.
     */
    public function isDataExfiltration(): bool
    {
        return $this !== self::Viewed;
    }
}
