<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Modules\Access\Domain\AccessPolicy;
use App\Modules\Access\Domain\OrganizationalScope;
use App\Modules\Access\Domain\Role;
use PHPUnit\Framework\TestCase;

/**
 * ARCH-001 §6.2 (Role × Organizational Scope × Ownership) dan §6.3
 * (persetujuan tidak boleh atas pengajuan milik sendiri).
 */
final class AccessPolicyTest extends TestCase
{
    public function test_pemilik_selalu_dapat_mengakses_datanya_sendiri_meski_di_luar_lingkup(): void
    {
        $policy = new AccessPolicy(Role::Pegawai, OrganizationalScope::selfOnly());

        $this->assertTrue($policy->canAccessRecord('kantor-lain', 'pegawai-1', 'pegawai-1'));
    }

    public function test_lingkup_office_tree_menolak_kantor_di_luar_pohon(): void
    {
        $policy = new AccessPolicy(
            Role::AtasanLangsung,
            OrganizationalScope::officeTree(['kc-mataram', 'kcp-praya']),
        );

        $this->assertTrue($policy->canAccessRecord('kc-mataram', 'pegawai-lain', 'atasan-1'));
        $this->assertFalse($policy->canAccessRecord('kc-selong', 'pegawai-lain', 'atasan-1'));
    }

    public function test_tanpa_kantor_dan_bukan_pemilik_selalu_ditolak(): void
    {
        $policy = new AccessPolicy(Role::HrApprover, OrganizationalScope::bankWide());

        $this->assertFalse($policy->canAccessRecord(null, 'pegawai-lain', 'aktor-1'));
    }

    public function test_persetujuan_atas_pengajuan_sendiri_dilarang(): void
    {
        $policy = new AccessPolicy(Role::AtasanLangsung, OrganizationalScope::officeTree(['kc-mataram']));

        $this->assertFalse($policy->canApprove('atasan-1', 'atasan-1'));
        $this->assertTrue($policy->canApprove('pegawai-lain', 'atasan-1'));
    }

    public function test_auditor_tidak_pernah_dapat_menyetujui_apa_pun(): void
    {
        $policy = new AccessPolicy(Role::Auditor, OrganizationalScope::bankWide());

        $this->assertFalse($policy->canApprove('pegawai-lain', 'auditor-1'));
    }
}
