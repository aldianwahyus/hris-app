<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain;

/**
 * Satu "subjek laporan" (Data Pegawai, Absensi, Cuti, dst) — registry
 * pattern (lihat ReportSubjectRegistry): setiap subjek mendefinisikan
 * SENDIRI join+kolom yang diizinkan, TIDAK ada builder SQL generik
 * bebas yang bisa disalahgunakan pengguna.
 *
 * Kontrak MURNI (hanya metadata) — pembangunan query SQL sungguhan
 * adalah kepentingan Infrastructure, lihat
 * Infrastructure\QueryableReportSubject (ARCH-001: Domain tidak boleh
 * bergantung pada framework).
 */
interface ReportSubject
{
    public function key(): string;

    public function label(): string;

    /** @return array<string, ReportColumn> kunci kolom → definisi, WHITELIST lengkap subjek ini */
    public function columns(): array;

    /** Kolom SQL dipakai filter rentang tanggal. */
    public function dateColumn(): string;

    /** Kolom SQL dipakai filter status. */
    public function statusColumn(): string;

    /** @return array<string, string> nilai status → label, kosong bila subjek ini tidak punya filter status */
    public function statusOptions(): array;
}
