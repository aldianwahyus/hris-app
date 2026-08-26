<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

/**
 * Sumbu ketiga: Ownership (ARCH-001 §6.2). Pemilik data SELALU dapat
 * mengakses miliknya sendiri, terlepas dari lingkup organisasi
 * perannya — inilah yang membuat Pegawai (lingkup SELF) tetap dapat
 * melihat pengajuannya sendiri tanpa perlu lingkup kantor apa pun.
 */
final readonly class Ownership
{
    public static function isOwnedBy(string $recordEmployeeId, string $actingEmployeeId): bool
    {
        return $recordEmployeeId === $actingEmployeeId;
    }
}
