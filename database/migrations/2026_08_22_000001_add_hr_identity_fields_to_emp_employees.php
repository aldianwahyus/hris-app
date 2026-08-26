<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field identitas & rekening HR tambahan — SEMUA lewat jalur
 * maker-checker yang sudah ada (EditableEmployeeField), BUKAN self-edit
 * CV Saya, walau sebagian terasa "data pribadi" (KTP/NPWP) — keputusan
 * sadar: tetap perlu usul-setuju HR untuk data identitas resmi ini.
 *
 * nomor_ktp SENGAJA TIDAK unique — kualitas data saat migrasi awal
 * belum tentu bersih, constraint bisa ditambah belakangan setelah HC
 * memastikan data bersih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->string('agama', 20)->nullable()->after('division');
            $table->string('nomor_ktp', 20)->nullable()->after('agama');
            $table->string('nomor_npwp', 25)->nullable()->after('nomor_ktp');
            $table->string('bpjs_tenaga_kerja', 30)->nullable()->after('nomor_npwp');
            $table->string('bpjs_kesehatan', 30)->nullable()->after('bpjs_tenaga_kerja');
            $table->string('nomor_simpeda', 30)->nullable()->after('bpjs_kesehatan');
            $table->string('nomor_tambora_rencana', 30)->nullable()->after('nomor_simpeda');
            $table->date('tmt_pangkat')->nullable()->after('nomor_tambora_rencana');
        });
    }

    public function down(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->dropColumn([
                'agama', 'nomor_ktp', 'nomor_npwp', 'bpjs_tenaga_kerja',
                'bpjs_kesehatan', 'nomor_simpeda', 'nomor_tambora_rencana', 'tmt_pangkat',
            ]);
        });
    }
};
