<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan gaji per pegawai — diinput admin cabang (hr_admin, maker)
 * SELAMA payroll run masih berstatus draft. Terkunci otomatis begitu
 * DecidePayrollRun::approve() mengubah status run (gerbangnya di
 * controller/Application, bukan di skema ini — tabel ini tidak tahu
 * status run induknya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_payslip_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payslip_id');
            $table->string('deduction_type', 30);
            $table->bigInteger('amount_cents');
            $table->text('note')->nullable();
            $table->uuid('created_by');

            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('payslip_id')->references('id')->on('pay_payslips')->cascadeOnDelete();
            $table->index('payslip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_payslip_deductions');
    }
};
