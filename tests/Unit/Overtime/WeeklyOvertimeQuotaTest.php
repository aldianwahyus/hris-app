<?php

declare(strict_types=1);

namespace Tests\Unit\Overtime;

use App\Modules\Overtime\Domain\WeeklyOvertimeCapExceeded;
use App\Modules\Overtime\Domain\WeeklyOvertimeQuota;
use PHPUnit\Framework\TestCase;

/**
 * Plafon lembur mingguan (DEC-31/36) — dihitung dari disetujui + menunggu
 * (DEC-32). Plafon 18 jam dipakai pada uji ini sebagai NILAI CONTOH yang
 * disuntikkan eksplisit (mencerminkan OVT_WEEKLY_CAP_HOURS yang berlaku
 * saat ini) — kelas ini sendiri tidak lagi mengetahui angka plafon.
 */
final class WeeklyOvertimeQuotaTest extends TestCase
{
    private const CAP = 18.0;

    public function test_mengizinkan_pengajuan_yang_masih_dalam_plafon(): void
    {
        $quota = new WeeklyOvertimeQuota(approvedHours: 10.0, pendingHours: 4.0, capHours: self::CAP);

        $this->assertTrue($quota->canAccommodate(4.0)); // 10+4+4=18, tepat di batas
    }

    public function test_menolak_pengajuan_yang_melampaui_plafon(): void
    {
        $quota = new WeeklyOvertimeQuota(approvedHours: 10.0, pendingHours: 4.0, capHours: self::CAP);

        $this->assertFalse($quota->canAccommodate(4.5)); // 18.5 > 18
    }

    public function test_menghitung_dari_disetujui_dan_menunggu_sekaligus(): void
    {
        $quota = new WeeklyOvertimeQuota(approvedHours: 0.0, pendingHours: 17.0, capHours: self::CAP);

        $this->assertFalse($quota->canAccommodate(2.0), 'Jam menunggu tetap harus dihitung — DEC-32.');
    }

    public function test_guard_melempar_ketika_melampaui(): void
    {
        $this->expectException(WeeklyOvertimeCapExceeded::class);

        (new WeeklyOvertimeQuota(approvedHours: 15.0, pendingHours: 0.0, capHours: self::CAP))->guard(5.0);
    }

    public function test_guard_tidak_melempar_ketika_dalam_batas(): void
    {
        (new WeeklyOvertimeQuota(approvedHours: 0.0, pendingHours: 0.0, capHours: self::CAP))->guard(18.0);

        $this->addToAssertionCount(1);
    }

    public function test_plafon_bersifat_parameter_bukan_konstanta(): void
    {
        // Seandainya SK berikutnya mengubah plafon menjadi 20 jam, kelas
        // ini harus mengikuti tanpa perubahan kode.
        $quota = new WeeklyOvertimeQuota(approvedHours: 18.0, pendingHours: 0.0, capHours: 20.0);

        $this->assertTrue($quota->canAccommodate(2.0), 'Dengan capHours=20, 18+2=20 masih dalam batas.');
    }
}
