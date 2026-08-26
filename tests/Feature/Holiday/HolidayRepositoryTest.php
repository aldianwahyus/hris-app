<?php

declare(strict_types=1);

namespace Tests\Feature\Holiday;

use App\Core\Domain\Uuid7;
use App\Shared\Holiday\Domain\HolidayRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EloquentHolidayRepository dites lewat interface (butuh DB/Cache
 * facade nyata, bukan test Unit murni — lihat SppdBudgetCalculatorTest
 * untuk pembanding kelas Domain TANPA dependensi framework).
 */
final class HolidayRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rentang_tanpa_libur_hanya_kecualikan_akhir_pekan(): void
    {
        // 2026-09-01 (Selasa) s.d. 2026-09-07 (Senin) = 5 hari kerja.
        $count = $this->repository()->countWorkingDays(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-07'),
        );

        $this->assertSame(5, $count);
    }

    public function test_hari_libur_yang_jatuh_di_akhir_pekan_tidak_dihitung_dua_kali(): void
    {
        // Sabtu 2026-09-05 didaftarkan sebagai libur — tetap harus
        // dikecualikan cuma SEKALI (bukan mengurangi total dua kali
        // lipat karena sudah akhir pekan juga).
        $this->seedHoliday('2026-09-05');

        $count = $this->repository()->countWorkingDays(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-07'),
        );

        $this->assertSame(5, $count);
    }

    public function test_rentang_penuh_di_dalam_satu_hari_libur(): void
    {
        $this->seedHoliday('2026-09-03');

        $count = $this->repository()->countWorkingDays(
            new DateTimeImmutable('2026-09-03'),
            new DateTimeImmutable('2026-09-03'),
        );

        $this->assertSame(0, $count);
    }

    public function test_mulai_dan_berakhir_tepat_di_hari_libur(): void
    {
        // 2026-09-01 (Selasa, libur) s.d. 2026-09-04 (Jumat, libur) —
        // Rabu & Kamis di antaranya tetap hari kerja.
        $this->seedHoliday('2026-09-01');
        $this->seedHoliday('2026-09-04');

        $count = $this->repository()->countWorkingDays(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-04'),
        );

        $this->assertSame(2, $count);
    }

    public function test_rentang_multi_minggu_dengan_beberapa_libur(): void
    {
        // 2026-09-01 s.d. 2026-09-30: 22 hari kerja mentah (tanpa akhir
        // pekan), dikurangi 2 hari libur nasional yang jatuh di hari
        // kerja (09-03 Kamis, 09-17 Kamis).
        $this->seedHoliday('2026-09-03');
        $this->seedHoliday('2026-09-17');

        $count = $this->repository()->countWorkingDays(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-30'),
        );

        $this->assertSame(20, $count);
    }

    public function test_is_holiday_dan_between(): void
    {
        $this->seedHoliday('2026-12-25', 'Natal');

        $this->assertTrue($this->repository()->isHoliday(new DateTimeImmutable('2026-12-25')));
        $this->assertFalse($this->repository()->isHoliday(new DateTimeImmutable('2026-12-24')));

        $between = $this->repository()->between(
            new DateTimeImmutable('2026-12-01'),
            new DateTimeImmutable('2026-12-31'),
        );

        $this->assertCount(1, $between);
        $this->assertSame('Natal', $between[0]->name);
    }

    private function seedHoliday(string $date, string $name = 'Uji Libur'): void
    {
        DB::table('cfg_national_holidays')->insert([
            'id' => (string) Uuid7::generate(),
            'holiday_date' => $date,
            'name' => $name,
            'is_national' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    private function repository(): HolidayRepository
    {
        return app(HolidayRepository::class);
    }
}
