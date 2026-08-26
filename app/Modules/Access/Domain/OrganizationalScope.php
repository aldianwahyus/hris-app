<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

/**
 * Lingkup organisasi seorang aktor — sumbu kedua dari model
 * Role × Organizational Scope × Ownership (ARCH-001 §6.2).
 *
 * Nilai `officeIds` sudah dihitung sebelumnya (mis. hasil penelusuran
 * hierarki kantor) — kelas ini murni memutuskan, tidak menelusuri.
 */
final readonly class OrganizationalScope
{
    /** @param array<int, string> $officeIds */
    private function __construct(
        public ScopeType $type,
        private array $officeIds = [],
    ) {}

    public static function selfOnly(): self
    {
        return new self(ScopeType::SelfOnly);
    }

    public static function office(string $officeId): self
    {
        return new self(ScopeType::Office, [$officeId]);
    }

    /** @param array<int, string> $officeIds kantor asal + seluruh turunannya */
    public static function officeTree(array $officeIds): self
    {
        return new self(ScopeType::OfficeTree, $officeIds);
    }

    public static function bankWide(): self
    {
        return new self(ScopeType::BankWide);
    }

    public function coversOffice(string $officeId): bool
    {
        return match ($this->type) {
            ScopeType::BankWide => true,
            ScopeType::Office, ScopeType::OfficeTree => in_array($officeId, $this->officeIds, true),
            ScopeType::SelfOnly => false,
        };
    }
}
