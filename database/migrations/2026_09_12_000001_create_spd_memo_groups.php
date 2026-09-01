<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPPD Massal — input berbasis memo divisi (Admin HC/Admin Cabang
 * menginput SEKALIGUS semua pegawai yang berangkat dalam satu memo
 * fisik), TERPISAH dari alur pengajuan mandiri (spd_requests biasa,
 * TIDAK diubah selain kolom memo_group_id di bawah).
 *
 * Baris spd_requests yang dibuat lewat jalur ini langsung berstatus
 * 'approved' dengan approver_id=NULL (memo = jejak persetujuan,
 * bukan Atasan Langsung/Pimpinan Kantor) — lihat SubmitSppdMemoGroup.
 * approver_id NULL membuatnya otomatis tidak muncul di
 * SppdDisbursementController::baseQuery() (INNER JOIN ke approver_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spd_memo_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('group_number', 40)->unique();
            $table->string('memo_number', 100);
            $table->date('memo_date');
            $table->string('source_division', 150)->nullable();
            $table->string('trip_category', 40);
            $table->string('radius_band', 20)->nullable();
            $table->string('destination', 200);
            $table->text('purpose');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('total_days');
            $table->string('currency', 3)->default('IDR');
            // Slot penandatangan GENERIK (judul+nama bebas diisi Admin HC,
            // tidak terikat role/pegawai tertentu di sistem) — dipakai
            // bersama oleh Surat Jalan (authorizing_official) dan setiap
            // Rincian Lumpsum pegawai dalam grup (lumpsum_signatory_1/2).
            $table->string('authorizing_official_title', 150)->nullable();
            $table->string('authorizing_official_name', 150)->nullable();
            $table->string('lumpsum_signatory_1_title', 150)->nullable();
            $table->string('lumpsum_signatory_1_name', 150)->nullable();
            $table->string('lumpsum_signatory_2_title', 150)->nullable();
            $table->string('lumpsum_signatory_2_name', 150)->nullable();
            $table->string('payer_scope', 10); // hc | branch
            $table->uuid('office_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('office_id')->references('id')->on('md_offices')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('emp_employees')->nullOnDelete();
            $table->index(['payer_scope', 'office_id']);
        });

        Schema::table('spd_requests', function (Blueprint $table) {
            $table->uuid('memo_group_id')->nullable()->after('id');
            $table->foreign('memo_group_id')->references('id')->on('spd_memo_groups')->restrictOnDelete();
            $table->index('memo_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('spd_requests', function (Blueprint $table) {
            $table->dropForeign(['memo_group_id']);
            $table->dropColumn('memo_group_id');
        });

        Schema::dropIfExists('spd_memo_groups');
    }
};
