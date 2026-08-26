<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Modules\Access\Domain\Role;
use App\Modules\Access\Domain\SegregationOfDutyPolicy;
use PHPUnit\Framework\TestCase;

/**
 * ARCH-001 §6.3 — tidak ada peran Super Admin tunggal. Pengujian ini
 * adalah pagar terhadap kombinasi peran yang melanggar pemisahan tugas.
 */
final class SegregationOfDutyPolicyTest extends TestCase
{
    private SegregationOfDutyPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new SegregationOfDutyPolicy;
    }

    public function test_pegawai_selalu_dapat_diberikan_terlepas_dari_peran_lain(): void
    {
        $this->assertTrue($this->policy->canAssign([], Role::Pegawai));
        $this->assertTrue($this->policy->canAssign([Role::Auditor], Role::Pegawai));
        $this->assertTrue($this->policy->canAssign([Role::HrAdmin], Role::Pegawai));
    }

    public function test_hr_admin_dan_hr_approver_saling_eksklusif(): void
    {
        $this->assertFalse($this->policy->canAssign([Role::Pegawai, Role::HrAdmin], Role::HrApprover));
        $this->assertFalse($this->policy->canAssign([Role::Pegawai, Role::HrApprover], Role::HrAdmin));
    }

    public function test_auditor_tidak_dapat_merangkap_peran_operasional(): void
    {
        $this->assertFalse($this->policy->canAssign([Role::Pegawai, Role::AtasanLangsung], Role::Auditor));
        $this->assertFalse($this->policy->canAssign([Role::Pegawai, Role::Auditor], Role::HrAdmin));
    }

    public function test_auditor_dapat_diberikan_pada_akun_yang_hanya_memegang_pegawai(): void
    {
        $this->assertTrue($this->policy->canAssign([Role::Pegawai], Role::Auditor));
        $this->assertTrue($this->policy->canAssign([], Role::Auditor));
    }

    public function test_atasan_langsung_tidak_terikat_kelompok_eksklusif_hr(): void
    {
        $this->assertTrue($this->policy->canAssign([Role::Pegawai, Role::HrAdmin], Role::AtasanLangsung));
        $this->assertTrue($this->policy->canAssign([Role::Pegawai, Role::HrApprover], Role::AtasanLangsung));
    }

    public function test_peran_yang_sudah_dimiliki_tidak_dianggap_pelanggaran_baru(): void
    {
        $this->assertTrue($this->policy->canAssign([Role::Pegawai, Role::HrAdmin], Role::HrAdmin));
    }
}
