<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Domain\Uuid7;
use PHPUnit\Framework\TestCase;

final class Uuid7Test extends TestCase
{
    public function test_menghasilkan_uuid_versi_7_yang_valid(): void
    {
        $uuid = Uuid7::generate();

        $this->assertTrue(Uuid7::isValid((string) $uuid));
        // Nibble pertama kelompok ketiga harus '7' (penanda versi)
        $this->assertSame('7', substr((string) $uuid, 14, 1));
    }

    public function test_bersifat_terurut_waktu_agar_indeks_tidak_terfragmentasi(): void
    {
        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = (string) Uuid7::generate();
            usleep(1200); // pastikan milidetik berbeda
        }

        $sorted = $ids;
        sort($sorted);

        // Inilah alasan memilih v7 atas v4: urutan pembuatan = urutan leksikal,
        // sehingga sisipan mendekati berurutan pada indeks B-Tree.
        $this->assertSame($sorted, $ids);
    }

    public function test_dapat_mengekstrak_waktu_pembuatan(): void
    {
        $before = (int) (microtime(true) * 1000);
        $uuid = Uuid7::generate();
        $after = (int) (microtime(true) * 1000);

        $this->assertGreaterThanOrEqual($before, $uuid->createdAtMilliseconds());
        $this->assertLessThanOrEqual($after, $uuid->createdAtMilliseconds());
    }
}
