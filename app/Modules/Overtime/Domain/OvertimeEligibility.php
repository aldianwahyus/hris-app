<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Domain;

/**
 * Kelayakan lembur per jabatan (DEC-90/BR-LB-06) — daftar jabatan
 * eksplisit (Division Head, Department Head, Desk Head, Branch Manager,
 * Sub Branch Manager) TIDAK berhak Lembur Biasa maupun Shift/Piket,
 * namun tetap berhak Crash Program. Ini enumerasi jabatan, BUKAN ambang
 * person grade — karena itu kelayakan disimpan sebagai flag pada data
 * master jabatan (md_positions.eligible_overtime_regular/_crash), bukan
 * dihitung dari nomor grade.
 */
final readonly class OvertimeEligibility
{
    public function __construct(
        public bool $eligibleForRegular,
        public bool $eligibleForCrashProgram,
    ) {}

    public function guard(OvertimeType $type): void
    {
        $eligible = $type === OvertimeType::CrashProgram
            ? $this->eligibleForCrashProgram
            : $this->eligibleForRegular;

        if (! $eligible) {
            throw OvertimeNotEligible::forType($type);
        }
    }
}
