<?php

declare(strict_types=1);

namespace App\Modules\Leave\Domain;

/**
 * Pengambilan cuti tahunan PERTAMA pada tahun berjalan wajib berupa
 * blok sekaligus minimal (BPP/1087/03/64/2026 butir 8.a) — mencegah
 * pemakaian mencicil harian yang mempersulit perencanaan operasional
 * kantor. Pengambilan berikutnya di tahun yang sama tidak lagi terikat
 * batas ini.
 *
 * Angka minimum BUKAN konstanta — parameter berversi LEAVE_BLOCK_LEAVE_MIN.
 */
final readonly class FirstLeaveBlockPolicy
{
    public function __construct(private float $minimumDays) {}

    public function guard(bool $isFirstTakenThisYear, float $requestedDays): void
    {
        if ($isFirstTakenThisYear && $requestedDays < $this->minimumDays) {
            throw FirstLeaveMustBeBlock::of($this->minimumDays, $requestedDays);
        }
    }
}
