<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

/** Empat lingkup organisasi (ARCH-001 §6.2). */
enum ScopeType: string
{
    case SelfOnly = 'self';
    case Office = 'office';
    case OfficeTree = 'office_tree';
    case BankWide = 'bank_wide';
}
