<?php

declare(strict_types=1);

namespace Tests\Unit\Overtime;

use App\Modules\Overtime\Domain\OvertimeEligibility;
use App\Modules\Overtime\Domain\OvertimeNotEligible;
use App\Modules\Overtime\Domain\OvertimeType;
use PHPUnit\Framework\TestCase;

/**
 * DEC-90/BR-LB-06 — Division Head, Department Head, Desk Head, Branch
 * Manager, Sub Branch Manager tidak berhak Lembur Biasa namun tetap
 * berhak Crash Program. Enumerasi jabatan eksplisit, bukan ambang grade.
 */
final class OvertimeEligibilityTest extends TestCase
{
    public function test_jabatan_pimpinan_ditolak_untuk_lembur_biasa(): void
    {
        // Cerminan Branch Manager: eligible_overtime_regular=false, eligible_overtime_crash=true.
        $eligibility = new OvertimeEligibility(eligibleForRegular: false, eligibleForCrashProgram: true);

        $this->expectException(OvertimeNotEligible::class);
        $eligibility->guard(OvertimeType::Regular);
    }

    public function test_jabatan_pimpinan_tetap_berhak_crash_program(): void
    {
        $eligibility = new OvertimeEligibility(eligibleForRegular: false, eligibleForCrashProgram: true);

        $eligibility->guard(OvertimeType::CrashProgram);

        $this->addToAssertionCount(1);
    }

    public function test_jabatan_pelaksana_berhak_keduanya(): void
    {
        $eligibility = new OvertimeEligibility(eligibleForRegular: true, eligibleForCrashProgram: true);

        $eligibility->guard(OvertimeType::Regular);
        $eligibility->guard(OvertimeType::CrashProgram);

        $this->addToAssertionCount(2);
    }

    public function test_shift_piket_mengikuti_kelayakan_lembur_biasa(): void
    {
        $eligibility = new OvertimeEligibility(eligibleForRegular: false, eligibleForCrashProgram: true);

        $this->expectException(OvertimeNotEligible::class);
        $eligibility->guard(OvertimeType::ShiftPicket);
    }
}
