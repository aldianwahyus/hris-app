<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi Berversi — DB-001 §3.2.
 *
 * Terbukti berulang: aturan bisnis Bank berubah melalui SK Direksi.
 * SK Lembur 2026 menggantikan SK 2024; BPP Cuti 2026 menggantikan 2024;
 * SPP Sanksi 2026 menggantikan 2024.
 *
 * UJI PENERIMAAN WAJIB: menghitung ulang upah lembur Mei 2026 harus
 * menghasilkan angka IDENTIK dengan pembayaran saat itu, meskipun tarif
 * berubah 1 Juli 2026. Ini syarat auditabilitas, bukan fitur tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_parameters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->string('value_type', 20);          // integer | decimal | money | boolean | string
            $table->string('unit', 50)->nullable();    // jam | hari | persen | rupiah
            $table->text('description')->nullable();
            $table->string('owner_module', 50);
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->integer('version')->default(1);
        });

        Schema::create('cfg_parameter_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parameter_id');
            $table->text('value');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_document', 150)->nullable(); // nomor SK yang mendasari
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->integer('version')->default(1);

            $table->foreign('parameter_id')->references('id')->on('cfg_parameters');
            $table->index(['parameter_id', 'effective_from']);
        });

        // Mencegah rentang keberlakuan tumpang tindih untuk parameter yang sama.
        // Tanpa ini, dua nilai bisa berlaku bersamaan dan perhitungan menjadi
        // tidak deterministik — persis jenis cacat yang sulit dilacak.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist;');
        DB::statement(<<<'SQL'
            ALTER TABLE cfg_parameter_values
            ADD CONSTRAINT excl_cfg_parameter_values_overlap
            EXCLUDE USING gist (
                parameter_id WITH =,
                daterange(effective_from, effective_to, '[]') WITH &&
            ) WHERE (deleted_at IS NULL);
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_parameter_values');
        Schema::dropIfExists('cfg_parameters');
    }
};
