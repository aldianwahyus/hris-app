<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manajemen Kontrak — modul baru (evaluasi PM/client 2026-09-02) untuk
 * pegawai kontrak/outsource. `renewed_from_contract_id` (self-FK)
 * merangkai riwayat perpanjangan — baris LAMA jadi status='diperpanjang'
 * saat baris BARU dibuat (lihat RenewContract), TIDAK PERNAH diedit
 * in-place supaya riwayat lengkap tetap terbaca. `reminder_sent_at`
 * mencegah pengingat H-N terkirim ganda tiap hari command berjalan
 * (pola SAMA `wf_instance_steps.reminders_sent` untuk SLA, disederhanakan
 * jadi satu ambang saja karena kontrak bukan proses persetujuan
 * berjenjang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('contract_number', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('contract_type', 20); // kontrak | outsource
            $table->string('status', 20)->default('aktif'); // aktif | diperpanjang | berakhir | diputus
            $table->uuid('renewed_from_contract_id')->nullable();
            $table->timestampTz('reminder_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index(['status', 'end_date']);
        });

        // FK swa-rujuk WAJIB ditambah SETELAH tabel benar-benar ada (Postgres
        // menolak "there is no unique constraint matching given keys" bila
        // dicoba dalam SATU Schema::create() yang sama).
        Schema::table('emp_contracts', function (Blueprint $table) {
            $table->foreign('renewed_from_contract_id')->references('id')->on('emp_contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_contracts');
    }
};
