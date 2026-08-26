<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow Engine — mendukung TIGA pola persetujuan (ARCH-001 §5.4).
 *
 * Definisi alur disimpan sebagai DATA, bukan kode, sesuai syarat Project
 * Constitution: "Workflow tidak boleh di-hardcode". Perubahan matriks
 * kewenangan cukup lewat konfigurasi, tanpa deployment ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Definisi alur per jenis dokumen — BERVERSI & bertanggal berlaku,
        // karena matriks kewenangan berubah melalui SK Direksi.
        Schema::create('wf_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type', 100);      // leave_request | overtime_request | violation_case
            $table->string('name', 200);
            $table->unsignedSmallInteger('revision')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_document', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->integer('version')->default(1);

            $table->index(['document_type', 'effective_from']);
        });

        // Langkah dalam alur. Kolom approval_pattern menentukan strategi
        // yang dipakai — BUKAN asumsi "approver = atasan langsung".
        Schema::create('wf_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('definition_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('name', 150);
            $table->string('approval_pattern', 20);    // individual | centralized | committee
            $table->unsignedSmallInteger('quorum')->nullable();
            $table->boolean('require_unanimous')->default(false);
            $table->unsignedSmallInteger('sla_days')->nullable();
            $table->json('reminder_days_before')->nullable();  // mis. [7, 3]
            $table->boolean('allow_delegation')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('definition_id')->references('id')->on('wf_definitions');
            $table->unique(['definition_id', 'sequence'], 'uq_wf_steps_definition_sequence');
        });

        // Aturan penentuan approver: matriks (jenis × jabatan × lokasi).
        // Contoh Cuti: Branch Manager memutus cuti tahunan pegawai di
        // kantor cabangnya. Contoh Lembur: Direktur Pembina memutus
        // seluruh lembur KC/KCP — memotong hierarki unit.
        Schema::create('wf_step_resolvers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('step_id');
            $table->string('resolver_type', 50);       // position | direct_supervisor | fixed_role | committee_member
            $table->string('subject_scope', 50)->nullable();   // head_office | branch | sub_branch
            $table->uuid('position_id')->nullable();
            $table->string('role_code', 100)->nullable();
            $table->json('conditions')->nullable();    // mis. {"leave_type":["CT","CB"]}
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('step_id')->references('id')->on('wf_steps');
            $table->index(['step_id', 'priority']);
        });

        // Instansi berjalan untuk satu pengajuan.
        Schema::create('wf_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('definition_id');
            $table->string('document_type', 100);
            $table->uuid('document_id');
            $table->uuid('initiator_id');
            $table->string('status', 20)->default('pending');  // pending | approved | rejected | cancelled | expired
            $table->unsignedSmallInteger('current_sequence')->default(1);
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('definition_id')->references('id')->on('wf_definitions');
            $table->index(['document_type', 'document_id']);
            $table->index(['status', 'started_at']);
        });

        // Status per langkah, termasuk SLA. Kolom pengingat memastikan
        // notifikasi H-7/H-3 tidak terkirim ganda maupun terlewat —
        // mitigasi risiko RA-3 (hak bayar pegawai hangus akibat
        // keterlambatan persetujuan).
        Schema::create('wf_instance_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instance_id');
            $table->uuid('step_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('sla_due_at')->nullable();
            $table->json('reminders_sent')->nullable();        // ambang yang sudah dikirim, mis. [7]
            $table->timestampTz('escalated_at')->nullable();
            $table->unsignedSmallInteger('quorum_required')->nullable();
            $table->unsignedSmallInteger('votes_received')->default(0);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('instance_id')->references('id')->on('wf_instances')->cascadeOnDelete();
            $table->foreign('step_id')->references('id')->on('wf_steps');

            // Dasbor antrean approver + basis penjadwalan pengingat SLA
            $table->index(['status', 'sla_due_at'], 'idx_wf_instance_steps_sla');
        });

        // Riwayat keputusan — sumber jejak audit persetujuan.
        Schema::create('wf_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instance_step_id');
            $table->uuid('actor_id');
            $table->string('action', 20);              // approved | rejected | delegated | escalated | cancelled
            $table->text('note')->nullable();
            $table->uuid('delegated_from')->nullable();
            $table->timestampTz('acted_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestampsTz();

            $table->foreign('instance_step_id')->references('id')->on('wf_instance_steps')->cascadeOnDelete();
            $table->index(['instance_step_id', 'acted_at']);
            $table->index(['actor_id', 'acted_at']);
        });

        // Suara anggota komite — khusus pola committee (KPP/KHC).
        Schema::create('wf_committee_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instance_step_id');
            $table->uuid('member_id');
            $table->boolean('approved');
            $table->text('note')->nullable();
            $table->timestampTz('voted_at');
            $table->timestampsTz();

            $table->foreign('instance_step_id')->references('id')->on('wf_instance_steps')->cascadeOnDelete();

            // Satu anggota satu suara per langkah — ditegakkan basis data,
            // bukan hanya aplikasi.
            $table->unique(['instance_step_id', 'member_id'], 'uq_wf_votes_step_member');
        });

        // Delegasi/substitusi approver, dibatasi periode.
        Schema::create('wf_delegations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('delegator_id');
            $table->uuid('delegate_id');
            $table->string('document_type', 100)->nullable();   // null = seluruh jenis
            $table->date('valid_from');
            $table->date('valid_to');
            $table->string('reason', 255)->nullable();
            $table->string('source_document', 150)->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->softDeletesTz();

            $table->index(['delegator_id', 'valid_from', 'valid_to']);
        });

        // Mencegah delegasi tumpang tindih untuk pemberi delegasi yang sama
        // pada jenis dokumen yang sama — menghindari dua delegate aktif
        // bersamaan, yang akan membuat penentuan approver ambigu.
        DB::statement(<<<'SQL'
            ALTER TABLE wf_delegations
            ADD CONSTRAINT excl_wf_delegations_overlap
            EXCLUDE USING gist (
                delegator_id WITH =,
                COALESCE(document_type, '*') WITH =,
                daterange(valid_from, valid_to, '[]') WITH &&
            ) WHERE (deleted_at IS NULL);
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_delegations');
        Schema::dropIfExists('wf_committee_votes');
        Schema::dropIfExists('wf_actions');
        Schema::dropIfExists('wf_instance_steps');
        Schema::dropIfExists('wf_instances');
        Schema::dropIfExists('wf_step_resolvers');
        Schema::dropIfExists('wf_steps');
        Schema::dropIfExists('wf_definitions');
    }
};
