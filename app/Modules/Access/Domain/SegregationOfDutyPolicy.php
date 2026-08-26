<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

/**
 * Aturan pemisahan tugas (ARCH-001 §6.3). Tidak ada peran Super Admin
 * tunggal — sebagai gantinya, kombinasi peran tertentu dilarang
 * dipegang satu orang sekaligus:
 *
 *  - HrAdmin (maker) dan HrApprover (checker) saling eksklusif, agar
 *    tidak ada satu orang yang membuat sekaligus mengesahkan perubahan.
 *  - Auditor harus independen: tidak boleh merangkap peran OPERASIONAL
 *    apa pun (AtasanLangsung/HrAdmin/HrApprover), dan sebaliknya.
 *
 * Pegawai DIKECUALIKAN dari pemeriksaan ini: itu peran dasar yang
 * dimiliki setiap akun (mengakses ESS miliknya sendiri, lingkup SELF),
 * bukan kewenangan operasional atas data pihak lain — Auditor pun
 * tetap seorang pegawai yang berhak melihat slip & cutinya sendiri.
 */
final readonly class SegregationOfDutyPolicy
{
    /** @var array<int, array<int, Role>> */
    private const MUTUALLY_EXCLUSIVE_GROUPS = [
        [Role::HrAdmin, Role::HrApprover],
    ];

    /** @param array<int, Role> $currentRoles */
    public function canAssign(array $currentRoles, Role $roleToAssign): bool
    {
        if ($roleToAssign === Role::Pegawai) {
            return true;
        }

        if (in_array($roleToAssign, $currentRoles, true)) {
            return true; // sudah punya peran ini — tidak menambah pelanggaran baru
        }

        $operationalRoles = array_values(array_filter(
            $currentRoles,
            static fn (Role $r): bool => $r !== Role::Pegawai,
        ));

        if ($roleToAssign === Role::Auditor && $operationalRoles !== []) {
            return false;
        }

        if (in_array(Role::Auditor, $operationalRoles, true)) {
            return false;
        }

        foreach (self::MUTUALLY_EXCLUSIVE_GROUPS as $group) {
            if (! in_array($roleToAssign, $group, true)) {
                continue;
            }

            foreach ($group as $exclusive) {
                if ($exclusive !== $roleToAssign && in_array($exclusive, $operationalRoles, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
