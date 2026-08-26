<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Domain\Pph21Golongan;
use PHPUnit\Framework\TestCase;

/** Pemetaan status PTKP -> Golongan TER (PMK 168/2023). */
final class Pph21GolonganTest extends TestCase
{
    public function test_tk0_dan_tk1_golongan_a(): void
    {
        $this->assertSame(Pph21Golongan::A, Pph21Golongan::fromStatus(married: false, dependents: 0));
        $this->assertSame(Pph21Golongan::A, Pph21Golongan::fromStatus(married: false, dependents: 1));
    }

    public function test_k0_golongan_a(): void
    {
        $this->assertSame(Pph21Golongan::A, Pph21Golongan::fromStatus(married: true, dependents: 0));
    }

    public function test_tk2_tk3_k1_k2_golongan_b(): void
    {
        $this->assertSame(Pph21Golongan::B, Pph21Golongan::fromStatus(married: false, dependents: 2));
        $this->assertSame(Pph21Golongan::B, Pph21Golongan::fromStatus(married: false, dependents: 3));
        $this->assertSame(Pph21Golongan::B, Pph21Golongan::fromStatus(married: true, dependents: 1));
        $this->assertSame(Pph21Golongan::B, Pph21Golongan::fromStatus(married: true, dependents: 2));
    }

    public function test_k3_golongan_c(): void
    {
        $this->assertSame(Pph21Golongan::C, Pph21Golongan::fromStatus(married: true, dependents: 3));
    }

    public function test_tanggungan_di_atas_3_dibatasi_ke_3(): void
    {
        $this->assertSame(Pph21Golongan::C, Pph21Golongan::fromStatus(married: true, dependents: 7));
        $this->assertSame('K/3', Pph21Golongan::C->ptkpLabel(married: true, dependents: 7));
    }
}
