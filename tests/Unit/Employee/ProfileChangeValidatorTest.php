<?php

declare(strict_types=1);

namespace Tests\Unit\Employee;

use App\Modules\Employee\Domain\InvalidProfileChange;
use App\Modules\Employee\Domain\ProfileChangeValidator;
use PHPUnit\Framework\TestCase;

final class ProfileChangeValidatorTest extends TestCase
{
    public function test_perubahan_kosong_ditolak(): void
    {
        $this->expectException(InvalidProfileChange::class);

        ProfileChangeValidator::validate([], []);
    }

    public function test_field_di_luar_whitelist_ditolak(): void
    {
        $this->expectException(InvalidProfileChange::class);

        ProfileChangeValidator::validate(['full_name' => 'Nama Baru'], ['full_name' => 'Nama Lama']);
    }

    public function test_status_tetap_tanpa_permanent_date_di_manapun_ditolak(): void
    {
        $this->expectException(InvalidProfileChange::class);

        ProfileChangeValidator::validate(
            ['employment_status' => 'tetap'],
            ['employment_status' => 'trainee', 'permanent_date' => null],
        );
    }

    public function test_status_tetap_dengan_permanent_date_yang_sudah_tercatat_diterima(): void
    {
        ProfileChangeValidator::validate(
            ['office_id' => 'kantor-baru'],
            ['employment_status' => 'tetap', 'permanent_date' => '2020-01-01'],
        );

        $this->addToAssertionCount(1); // tidak melempar
    }

    public function test_status_tetap_dengan_permanent_date_diajukan_bersamaan_diterima(): void
    {
        ProfileChangeValidator::validate(
            ['employment_status' => 'tetap', 'permanent_date' => '2026-09-01'],
            ['employment_status' => 'trainee', 'permanent_date' => null],
        );

        $this->addToAssertionCount(1);
    }

    public function test_perubahan_ke_status_non_tetap_tidak_perlu_permanent_date(): void
    {
        ProfileChangeValidator::validate(
            ['employment_status' => 'outsource'],
            ['employment_status' => 'tetap', 'permanent_date' => null],
        );

        $this->addToAssertionCount(1);
    }

    public function test_fields_to_snapshot_selalu_menyertakan_status_dan_permanent_date(): void
    {
        $fields = ProfileChangeValidator::fieldsToSnapshot(['office_id' => 'x']);

        $this->assertContains('office_id', $fields);
        $this->assertContains('employment_status', $fields);
        $this->assertContains('permanent_date', $fields);
    }
}
