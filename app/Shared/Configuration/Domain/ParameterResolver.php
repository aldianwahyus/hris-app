<?php

declare(strict_types=1);

namespace App\Shared\Configuration\Domain;

use App\Core\Domain\Money;
use App\Shared\Temporal\Domain\AsOfDate;

/**
 * Pembaca parameter kebijakan yang berlaku pada TANGGAL TERTENTU.
 *
 * Inilah yang membuat sistem dapat diaudit. Tarif lembur berubah dari
 * SK 2024 ke SK 2026; menghitung ulang upah lembur Mei 2026 harus
 * menghasilkan angka IDENTIK dengan yang dibayarkan saat itu — bukan
 * memakai tarif terbaru.
 *
 * Karena itu setiap pembacaan WAJIB menyertakan tanggal acuan
 * (AsOfDate), bukan parameter opsional yang mudah terlupa.
 */
interface ParameterResolver
{
    public function integer(string $code, AsOfDate $asOf): int;

    public function decimal(string $code, AsOfDate $asOf): float;

    public function money(string $code, AsOfDate $asOf): Money;

    public function string(string $code, AsOfDate $asOf): string;

    public function boolean(string $code, AsOfDate $asOf): bool;

    /** Mengembalikan nomor SK yang mendasari nilai — untuk jejak audit. */
    public function sourceDocument(string $code, AsOfDate $asOf): ?string;
}
