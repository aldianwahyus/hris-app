<?php

declare(strict_types=1);

namespace App\Modules\Izin\Domain;

enum IzinCategory: string
{
    case Sakit = 'sakit';
    case KeperluanKeluarga = 'keperluan_keluarga';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Sakit => 'Sakit',
            self::KeperluanKeluarga => 'Keperluan Keluarga',
            self::Lainnya => 'Lainnya',
        };
    }

    /** Sakit wajib lampiran bukti (mis. surat dokter) — kategori lain opsional. */
    public function requiresAttachment(): bool
    {
        return $this === self::Sakit;
    }
}
