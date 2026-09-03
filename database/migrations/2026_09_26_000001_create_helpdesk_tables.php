<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Helpdesk / Case Management — modul baru (evaluasi PM/client
 * 2026-09-02). Tiket pertanyaan/keluhan administratif pegawai ke HC
 * (`hd_tickets`) + thread balasan dua arah (`hd_ticket_replies`,
 * append-only — `is_internal_note` untuk catatan internal HC yang
 * TIDAK ditampilkan ke pegawai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hd_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number', 30)->unique();
            $table->uuid('employee_id');
            $table->string('category', 50);
            $table->string('subject', 150);
            $table->text('description');
            $table->string('status', 20)->default('terbuka'); // terbuka | diproses | selesai | ditutup
            $table->string('priority', 10)->default('sedang'); // rendah | sedang | tinggi
            $table->uuid('assigned_to')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('assigned_to')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('status');
        });

        Schema::create('hd_ticket_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->uuid('author_employee_id');
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->timestampTz('created_at');

            $table->foreign('ticket_id')->references('id')->on('hd_tickets')->restrictOnDelete();
            $table->foreign('author_employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_ticket_replies');
        Schema::dropIfExists('hd_tickets');
    }
};
