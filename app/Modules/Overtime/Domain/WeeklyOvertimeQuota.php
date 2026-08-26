<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

/**
 * Plafon 18 jam/minggu (DEC-31/DEC-36) — PENOLAKAN KERAS, dihitung dari
 * pengajuan DISETUJUI + MENUNGGU (DEC-32), bukan hanya yang sudah
 * disetujui. Kelas ini murni memutuskan; penguncian baris
 * (SELECT FOR UPDATE) yang mencegah kondisi balapan antar pengajuan
 * bersamaan adalah tanggung jawab Infrastructure/Application.
 *
 * Angka plafon BUKAN konstanta di sini — ini parameter berversi
 * (OVT_WEEKLY_CAP_HOURS, sumber KEP/1827/06/64/2026) yang dapat berubah
 * lewat SK Direksi berikutnya. Pemanggil (Application) wajib membacanya
 * dari ParameterResolver dan menyuntikkannya, agar perhitungan historis
 * tetap benar meski tarif berjalan sudah berubah.
 */
final readonly class WeeklyOvertimeQuota
{
    public function __construct(
        public float $approvedHours,
        public float $pendingHours,
        private float $capHours,
    ) {}

    public function totalReserved(): float
    {
        return $this->approvedHours + $this->pendingHours;
    }

    public function canAccommodate(float $requestedHours): bool
    {
        return $this->totalReserved() + $requestedHours <= $this->capHours;
    }

    public function guard(float $requestedHours): void
    {
        if (! $this->canAccommodate($requestedHours)) {
            throw WeeklyOvertimeCapExceeded::forRequest($requestedHours, $this->totalReserved(), $this->capHours);
        }
    }
}
