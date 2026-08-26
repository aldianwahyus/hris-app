<?php

declare(strict_types=1);

namespace App\Shared\Configuration\Domain;

use App\Shared\Temporal\Domain\AsOfDate;
use RuntimeException;

/**
 * Dilempar bila parameter tidak ditemukan untuk tanggal yang diminta.
 *
 * SENGAJA melempar exception, bukan mengembalikan nilai bawaan.
 * Nilai bawaan yang diam-diam dipakai pada perhitungan gaji adalah
 * sumber kesalahan finansial yang sulit dilacak — lebih baik gagal
 * keras dan terlihat daripada membayar dengan angka yang salah.
 */
final class ParameterNotFoundException extends RuntimeException
{
    public static function forCode(string $code, AsOfDate $asOf): self
    {
        return new self(sprintf(
            'Parameter "%s" tidak memiliki nilai yang berlaku pada %s. '
            . 'Periksa cfg_parameter_values — kemungkinan periode keberlakuan belum diisi.',
            $code,
            $asOf->toDateString()
        ));
    }
}
