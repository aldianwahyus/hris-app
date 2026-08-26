<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\AttendanceOvertimePolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AttendanceOvertimePolicyTest extends TestCase
{
    public function test_pulang_sebelum_jam_kerja_resmi_tidak_ada_lembur(): void
    {
        $hours = AttendanceOvertimePolicy::determineOvertimeHours(
            new DateTimeImmutable('2026-08-18 08:00:00'),
            new DateTimeImmutable('2026-08-18 16:30:00'),
            '17:00',
            30,
        );

        $this->assertNull($hours);
    }

    public function test_pulang_dalam_ambang_toleransi_tidak_dianggap_lembur(): void
    {
        $hours = AttendanceOvertimePolicy::determineOvertimeHours(
            new DateTimeImmutable('2026-08-18 08:00:00'),
            new DateTimeImmutable('2026-08-18 17:10:00'), // 10 menit lewat, ambang 30 menit
            '17:00',
            30,
        );

        $this->assertNull($hours);
    }

    public function test_pulang_melewati_ambang_dihitung_selisih_penuh_bukan_dikurangi_ambang(): void
    {
        $hours = AttendanceOvertimePolicy::determineOvertimeHours(
            new DateTimeImmutable('2026-08-18 08:00:00'),
            new DateTimeImmutable('2026-08-18 19:30:00'), // 2,5 jam lewat jam kerja resmi
            '17:00',
            30,
        );

        $this->assertSame(2.5, $hours);
    }

    /**
     * Regresi: lembur panjang yang pulang lewat tengah malam membuat
     * check-out sudah berada di KALENDER HARI BERIKUTNYA. Sebelum
     * diperbaiki, jam kerja resmi dijangkarkan pada tanggal check-out
     * (bukan check-in) — sehingga ikut bergeser ke hari berikutnya dan
     * salah menyimpulkan check-out < jam kerja resmi = "tidak ada
     * lembur", padahal pegawai bekerja 8 jam lembur penuh.
     */
    public function test_pulang_lewat_tengah_malam_tetap_dihitung_benar(): void
    {
        $hours = AttendanceOvertimePolicy::determineOvertimeHours(
            new DateTimeImmutable('2026-08-18 08:00:00'),
            new DateTimeImmutable('2026-08-19 01:00:00'), // pulang 01:00 keesokan harinya
            '17:00',
            30,
        );

        $this->assertSame(8.0, $hours);
    }

    public function test_format_jam_kerja_tidak_valid_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AttendanceOvertimePolicy::determineOvertimeHours(
            new DateTimeImmutable('2026-08-18 08:00:00'),
            new DateTimeImmutable('2026-08-18 19:00:00'),
            '5:00 PM',
            30,
        );
    }
}
