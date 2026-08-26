<?php

declare(strict_types=1);

namespace App\Core\Domain;

use InvalidArgumentException;

/**
 * Nilai uang dalam Rupiah — disimpan sebagai bilangan bulat SEN.
 *
 * DB-001 keputusan D-8: FLOAT/DOUBLE DILARANG untuk uang. Kesalahan
 * pembulatan tidak dapat diterima pada sistem yang menghitung gaji,
 * Bekal Cuti (1–2× gaji bulanan × 1.000+ pegawai), dan PPh 21.
 *
 * Di basis data dipetakan ke NUMERIC(18,2).
 */
final readonly class Money
{
    private function __construct(public int $cents)
    {
    }

    public static function fromRupiah(int|float|string $rupiah): self
    {
        $cents = (int) round((float) $rupiah * 100);

        return new self($cents);
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiplyBy(int|float $factor): self
    {
        return new self((int) round($this->cents * $factor));
    }

    /** Persentase dengan pembulatan setengah ke atas, mis. iuran 7% / 8,05% / 9,24%. */
    public function percentage(float $percent): self
    {
        if ($percent < 0) {
            throw new InvalidArgumentException('Persentase tidak boleh negatif.');
        }

        return new self((int) round($this->cents * $percent / 100));
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function toRupiah(): float
    {
        return $this->cents / 100;
    }

    public function format(): string
    {
        return 'Rp' . number_format($this->toRupiah(), 0, ',', '.');
    }
}
