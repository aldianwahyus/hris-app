<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Modules\Access\Domain\OrganizationalScope;
use PHPUnit\Framework\TestCase;

/** ARCH-001 §6.2 — sumbu Organizational Scope. */
final class OrganizationalScopeTest extends TestCase
{
    public function test_self_only_tidak_pernah_mencakup_kantor_mana_pun(): void
    {
        $this->assertFalse(OrganizationalScope::selfOnly()->coversOffice('kc-mataram'));
    }

    public function test_office_hanya_mencakup_kantor_yang_sama(): void
    {
        $scope = OrganizationalScope::office('kc-mataram');

        $this->assertTrue($scope->coversOffice('kc-mataram'));
        $this->assertFalse($scope->coversOffice('kc-selong'));
    }

    public function test_office_tree_mencakup_kantor_asal_dan_turunannya(): void
    {
        $scope = OrganizationalScope::officeTree(['kc-mataram', 'kcp-praya']);

        $this->assertTrue($scope->coversOffice('kc-mataram'));
        $this->assertTrue($scope->coversOffice('kcp-praya'));
        $this->assertFalse($scope->coversOffice('kc-selong'));
    }

    public function test_bank_wide_mencakup_kantor_mana_pun(): void
    {
        $scope = OrganizationalScope::bankWide();

        $this->assertTrue($scope->coversOffice('kc-mataram'));
        $this->assertTrue($scope->coversOffice('kantor-mana-saja'));
    }
}
