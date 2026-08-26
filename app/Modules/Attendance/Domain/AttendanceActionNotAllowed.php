<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

use DomainException;

/**
 * Aksi absen yang tidak sah pada URUTAN saat ini (beda dari
 * AlreadyCompletedToday, yang khusus untuk "hari ini sudah selesai
 * total", dan BreakNotYetAllowed, yang khusus jendela waktu Istirahat/
 * Kembali) — mis. Kembali tanpa pernah Istirahat, Pulang saat masih
 * tercatat sedang istirahat, atau Istirahat sebelum Masuk.
 */
final class AttendanceActionNotAllowed extends DomainException
{
    public static function mustCheckInFirst(): self
    {
        return new self('Anda belum absen masuk hari ini.');
    }

    public static function alreadyCheckedIn(): self
    {
        return new self('Absen masuk hari ini sudah tercatat.');
    }

    public static function breakAlreadyStarted(): self
    {
        return new self('Istirahat hari ini sudah tercatat.');
    }

    public static function breakNotStarted(): self
    {
        return new self('Anda belum mencatat mulai istirahat hari ini.');
    }

    public static function breakAlreadyEnded(): self
    {
        return new self('Kembali dari istirahat hari ini sudah tercatat.');
    }

    /** Pulang diblokir selama masih tercatat sedang istirahat — wajib Kembali dulu. */
    public static function mustEndBreakFirst(): self
    {
        return new self('Catat "Kembali" dari istirahat terlebih dahulu sebelum absen pulang.');
    }
}
