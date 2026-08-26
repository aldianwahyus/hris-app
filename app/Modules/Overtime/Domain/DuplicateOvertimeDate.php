<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Pegawai sudah punya pengajuan lembur "hidup" (bukan ditolak/
 * kedaluwarsa) untuk tanggal kerja yang sama — satu tanggal hanya
 * boleh punya SATU pengajuan lembur yang berlaku, supaya tidak ada
 * dua SPKL berbeda untuk hari yang sama yang berisiko keduanya lolos
 * pencairan (lihat juga guard tanggal-ganda di
 * ProcessOvertimePaymentBatch, lapis pertahanan kedua di sisi
 * pembayaran).
 */
final class DuplicateOvertimeDate extends DomainException
{
    public static function forDate(DateTimeImmutable $workDate): self
    {
        return new self(sprintf(
            'Pegawai ini sudah punya pengajuan lembur untuk tanggal %s (menunggu, disetujui, atau sudah dibayar) — tidak bisa mengajukan lembur dua kali di tanggal yang sama.',
            $workDate->format('d-m-Y'),
        ));
    }
}
