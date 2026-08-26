<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalender hari libur nasional — dikelola dinamis oleh SYSADMIN/Admin HC
 * (lihat NationalHolidayController), menggantikan ketiadaan rujukan
 * kalender kerja yang sebelumnya diakui sebagai celah di
 * AttendanceRecapController::countWorkingDays() dan
 * HcDashboardController (tanpaCatatanHariIni).
 *
 * is_national=false menandai cuti bersama (bukan libur resmi UU) —
 * tetap dikecualikan dari hitungan hari kerja, hanya beda label untuk
 * pelaporan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_national_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('holiday_date');
            $table->string('name', 150);
            $table->boolean('is_national')->default(true);
            $table->string('source_document', 150)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->uuid('created_by')->nullable();
            $table->integer('version')->default(1);

            $table->unique('holiday_date', 'uq_cfg_national_holidays_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_national_holidays');
    }
};
