<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Jendela waktu persetujuan beserta ambang pengingat.
 *
 * MENGAPA INI WAJIB, BUKAN OPSIONAL:
 *
 * Kebijakan lembur menggabungkan dua ketentuan yang, bila dibiarkan,
 * merugikan pegawai karena kelalaian pihak lain:
 *   - Seluruh persetujuan lembur terpusat pada SATU pejabat untuk
 *     puluhan cabang (DEC-92)
 *   - Keterlambatan persetujuan MENGHANGUSKAN hak bayar pegawai (DEC-94)
 *
 * Artinya pegawai kehilangan upah bila Direktur terlambat memutus —
 * padahal pegawai sudah bekerja. Keputusan kebijakannya bukan wewenang
 * tim teknis untuk diubah, namun arsitektur WAJIB menyediakan mitigasi:
 * pengingat H-7 dan H-3, dasbor antrean, dan eskalasi.
 *
 * Lihat risiko RA-3 pada ARCH-001 §10.
 */
final readonly class SlaWindow
{
    /** @param array<int,int> $reminderDaysBefore */
    public function __construct(
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $dueAt,
        public array $reminderDaysBefore = [7, 3],
    ) {
        if ($dueAt <= $startedAt) {
            throw new InvalidArgumentException('Tenggat SLA harus setelah waktu mulai.');
        }
    }

    public static function days(DateTimeImmutable $startedAt, int $days): self
    {
        return new self($startedAt, $startedAt->modify("+{$days} days"));
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return $now > $this->dueAt;
    }

    public function remainingDays(DateTimeImmutable $now): int
    {
        if ($this->isOverdue($now)) {
            return 0;
        }

        return (int) $now->diff($this->dueAt)->days;
    }

    /**
     * Menentukan ambang pengingat yang harus dikirim SAAT INI.
     *
     * Mengembalikan ambang terbesar yang sudah terlewati namun belum
     * pernah dikirim. Urutan menurun penting: pada H-6 dengan pengingat
     * H-7 sudah terkirim, fungsi harus mengembalikan null (belum saatnya
     * H-3) — bukan melewatkan H-3 selamanya.
     */
    public function shouldRemindNow(DateTimeImmutable $now, array $alreadySentFor = []): ?int
    {
        $remaining = $this->remainingDays($now);

        $thresholds = $this->reminderDaysBefore;
        rsort($thresholds);

        foreach ($thresholds as $threshold) {
            if ($remaining > $threshold) {
                continue; // ambang ini belum tercapai
            }

            if (in_array($threshold, $alreadySentFor, true)) {
                continue; // sudah pernah dikirim, periksa ambang berikutnya
            }

            return $threshold;
        }

        return null;
    }
}
