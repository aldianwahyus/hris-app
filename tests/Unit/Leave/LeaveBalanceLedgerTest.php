<?php

declare(strict_types=1);

namespace Tests\Unit\Leave;

use App\Modules\Leave\Domain\BucketType;
use App\Modules\Leave\Domain\InsufficientLeaveBalance;
use App\Modules\Leave\Domain\LeaveBalanceLedger;
use App\Modules\Leave\Domain\LeaveBucket;
use PHPUnit\Framework\TestCase;

/**
 * Urutan konsumsi kantong: jatah tahun berjalan (order 1) habis dulu,
 * baru sisa tahun lalu (order 2) tersentuh — hanya kantong tahun
 * berjalan yang memberi hak bekal cuti, sehingga urutan ini bukan
 * detail teknis melainkan aturan yang berdampak finansial.
 */
final class LeaveBalanceLedgerTest extends TestCase
{
    private function buckets(): array
    {
        return [
            new LeaveBucket(BucketType::CarryForward, quotaDays: 2.0, usedDays: 0.0, consumptionOrder: 2),
            new LeaveBucket(BucketType::CurrentYear, quotaDays: 12.0, usedDays: 9.0, consumptionOrder: 1),
        ];
    }

    public function test_konsumsi_penuh_dari_kantong_tahun_berjalan_bila_cukup(): void
    {
        $plan = (new LeaveBalanceLedger($this->buckets()))->planConsumption(2.0);

        $this->assertCount(1, $plan);
        $this->assertSame(BucketType::CurrentYear, $plan[0]->bucketType);
        $this->assertSame(2.0, $plan[0]->days);
    }

    public function test_meluap_ke_kantong_sisa_tahun_lalu_setelah_tahun_berjalan_habis(): void
    {
        // Sisa tahun berjalan hanya 3.0 (12-9); permintaan 4.0 hari.
        $plan = (new LeaveBalanceLedger($this->buckets()))->planConsumption(4.0);

        $this->assertCount(2, $plan);
        $this->assertSame(BucketType::CurrentYear, $plan[0]->bucketType);
        $this->assertSame(3.0, $plan[0]->days);
        $this->assertSame(BucketType::CarryForward, $plan[1]->bucketType);
        $this->assertSame(1.0, $plan[1]->days);
    }

    public function test_menolak_bila_kedua_kantong_tidak_mencukupi(): void
    {
        $this->expectException(InsufficientLeaveBalance::class);

        // Total tersedia 3.0 + 2.0 = 5.0; diminta 6.0.
        (new LeaveBalanceLedger($this->buckets()))->planConsumption(6.0);
    }

    public function test_urutan_masukan_tidak_memengaruhi_urutan_konsumsi(): void
    {
        $reversed = array_reverse($this->buckets());

        $plan = (new LeaveBalanceLedger($reversed))->planConsumption(4.0);

        $this->assertSame(BucketType::CurrentYear, $plan[0]->bucketType, 'Order konsumsi wajib mengikuti consumption_order, bukan urutan array.');
    }
}
