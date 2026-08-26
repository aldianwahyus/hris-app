<?php

declare(strict_types=1);

namespace App\Core\Domain;

use InvalidArgumentException;

/**
 * UUID versi 7 (time-ordered) — DB-001 keputusan D-1.
 *
 * UUID v4 bersifat acak sehingga menyebabkan fragmentasi indeks B-Tree
 * dan penurunan kinerja tulis pada tabel bervolume tinggi (mis. absensi
 * ±1.000 baris/hari). UUID v7 menempatkan timestamp milidetik pada 48 bit
 * pertama sehingga sisipan mendekati berurutan seperti sequence, namun
 * tetap tidak dapat ditebak karena 74 bit sisanya acak.
 *
 * Layout (RFC 9562):
 *   48 bit  unix_ts_ms
 *    4 bit  version (0111)
 *   12 bit  rand_a
 *    2 bit  variant (10)
 *   62 bit  rand_b
 */
final readonly class Uuid7
{
    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        $timestampMs = (int) (microtime(true) * 1000);

        // 48 bit timestamp, big-endian
        $bytes = pack('J', $timestampMs);
        $bytes = substr($bytes, 2); // buang 2 byte teratas → sisa 6 byte
        $bytes .= random_bytes(10);

        // Set version 7 pada nibble atas byte ke-7
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70);
        // Set variant RFC 4122 pada 2 bit teratas byte ke-9
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return new self(self::format($bytes));
    }

    public static function fromString(string $value): self
    {
        if (! self::isValid($value)) {
            throw new InvalidArgumentException("UUID tidak valid: {$value}");
        }

        return new self(strtolower($value));
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }

    /** Ekstrak waktu pembuatan dari UUID v7 — berguna untuk audit & debugging. */
    public function createdAtMilliseconds(): int
    {
        $hex = substr(str_replace('-', '', $this->value), 0, 12);

        return (int) hexdec($hex);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function format(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
