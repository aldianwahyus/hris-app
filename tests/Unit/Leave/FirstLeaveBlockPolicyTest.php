<?php

declare(strict_types=1);

namespace Tests\Unit\Leave;

use App\Modules\Leave\Domain\FirstLeaveBlockPolicy;
use App\Modules\Leave\Domain\FirstLeaveMustBeBlock;
use PHPUnit\Framework\TestCase;

/**
 * Angka minimum 5 hari dipakai di sini sebagai NILAI CONTOH yang
 * disuntikkan eksplisit (mencerminkan LEAVE_BLOCK_LEAVE_MIN yang
 * berlaku saat ini, BPP/1087/03/64/2026 butir 8.a) — kelas ini sendiri
 * tidak lagi mengetahui angkanya.
 */
final class FirstLeaveBlockPolicyTest extends TestCase
{
    private FirstLeaveBlockPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new FirstLeaveBlockPolicy(minimumDays: 5.0);
    }

    public function test_menolak_pengambilan_pertama_kurang_dari_batas_minimum(): void
    {
        $this->expectException(FirstLeaveMustBeBlock::class);

        $this->policy->guard(isFirstTakenThisYear: true, requestedDays: 3.0);
    }

    public function test_mengizinkan_pengambilan_pertama_tepat_batas_minimum(): void
    {
        $this->policy->guard(isFirstTakenThisYear: true, requestedDays: 5.0);

        $this->addToAssertionCount(1); // tidak melempar
    }

    public function test_mengizinkan_pengambilan_pertama_lebih_dari_batas_minimum(): void
    {
        $this->policy->guard(isFirstTakenThisYear: true, requestedDays: 7.0);

        $this->addToAssertionCount(1);
    }

    public function test_pengambilan_kedua_tidak_terikat_batas_minimal(): void
    {
        $this->policy->guard(isFirstTakenThisYear: false, requestedDays: 1.0);

        $this->addToAssertionCount(1);
    }

    public function test_batas_minimum_bersifat_parameter_bukan_konstanta(): void
    {
        // Seandainya BPP berikutnya mengubah batas menjadi 3 hari.
        $policy = new FirstLeaveBlockPolicy(minimumDays: 3.0);

        $policy->guard(isFirstTakenThisYear: true, requestedDays: 3.0);

        $this->addToAssertionCount(1);
    }
}
