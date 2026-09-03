<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding Terstruktur — modul baru (evaluasi PM/client 2026-09-02).
 * Template checklist reusable per employment_status
 * (`onb_checklist_templates`/`onb_checklist_template_items`) disalin
 * (SNAPSHOT, bukan referensi FK) ke checklist milik satu pegawai baru
 * saat pengajuan pegawai baru disetujui
 * (`onb_employee_checklists`/`onb_employee_checklist_items`) — lihat
 * GenerateOnboardingChecklist, dipicu dari
 * EmployeeApprovalQueueController::approveNewEmployee() persis
 * seperti triggerBekalCutiIfFirstThisYear pada Cuti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onb_checklist_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('employment_status_scope', 20)->nullable(); // NULL = berlaku untuk semua status
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->index(['employment_status_scope', 'is_active']);
        });

        Schema::create('onb_checklist_template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->string('item_name', 200);
            $table->string('category', 20); // it | hc | fasilitas | lainnya
            $table->integer('display_order')->default(0);

            $table->foreign('template_id')->references('id')->on('onb_checklist_templates')->restrictOnDelete();
            $table->index('template_id');
        });

        Schema::create('onb_employee_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('template_id');
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();

            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('template_id')->references('id')->on('onb_checklist_templates')->restrictOnDelete();
            $table->unique('employee_id');
        });

        Schema::create('onb_employee_checklist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('checklist_id');
            $table->string('item_name', 200);
            $table->string('category', 20);
            $table->boolean('is_done')->default(false);
            $table->uuid('done_by')->nullable();
            $table->timestampTz('done_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreign('checklist_id')->references('id')->on('onb_employee_checklists')->restrictOnDelete();
            $table->foreign('done_by')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('checklist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onb_employee_checklist_items');
        Schema::dropIfExists('onb_employee_checklists');
        Schema::dropIfExists('onb_checklist_template_items');
        Schema::dropIfExists('onb_checklist_templates');
    }
};
