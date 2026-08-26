<?php

declare(strict_types=1);

namespace App\Shared\Temporal\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Rentang keberlakuan — pola Effective Dating (DB-001 §2.1).
 *
 * Masalah nyata yang diselesaikan: SK KEP/1831/03/64/2026 mengubah struktur
 * Risk Management Division pada 30 Juni 2026. Bila sistem hanya menyimpan
 * "kondisi terkini", pertanyaan "siapa approver cuti pegawai X pada Maret
 * 2026?" akan dijawab dengan struktur bulan Juli — jawaban yang salah dan
 * akan menjadi temuan audit.
 *
 * effectiveTo = null berarti masih berlaku.
 */
final readonly class EffectivePeriod
{
    public function __construct(
        public DateTimeImmutable $effectiveFrom,
        public ?DateTimeImmutable $effectiveTo = null,
    ) {
        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw new InvalidArgumentException(
                'Tanggal berakhir tidak boleh mendahului tanggal mulai berlaku.'
            );
        }
    }

    public static function startingFrom(DateTimeImmutable $from): self
    {
        return new self($from);
    }

    public function contains(DateTimeImmutable $date): bool
    {
        if ($date < $this->effectiveFrom) {
            return false;
        }

        return $this->effectiveTo === null || $date <= $this->effectiveTo;
    }

    public function overlaps(self $other): bool
    {
        $thisEnd = $this->effectiveTo;
        $otherEnd = $other->effectiveTo;

        if ($thisEnd !== null && $other->effectiveFrom > $thisEnd) {
            return false;
        }

        if ($otherEnd !== null && $this->effectiveFrom > $otherEnd) {
            return false;
        }

        return true;
    }

    public function isOpenEnded(): bool
    {
        return $this->effectiveTo === null;
    }

    /** Menutup periode sehari sebelum periode baru mulai berlaku. */
    public function closedBefore(DateTimeImmutable $newStart): self
    {
        return new self($this->effectiveFrom, $newStart->modify('-1 day'));
    }
}
