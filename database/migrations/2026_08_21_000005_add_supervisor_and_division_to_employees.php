<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah hubungan atasan-bawahan PER ORANG (supervisor_id) dan
 * pengelompokan divisi (division) — sebelumnya TIDAK ADA sama sekali;
 * wewenang AtasanLangsung/PimpinanKantor selalu berbasis lingkup kantor
 * (OFFICE_TREE/OFFICE), BUKAN relasi personal. Dua kolom baru ini
 * MURNI untuk keperluan visual Struktur Organisasi
 * (OrganizationChartController) — TIDAK dipakai untuk otorisasi
 * (AccessPolicy/OrganizationalScope tetap seperti sekarang).
 *
 * division nullable & bebas isi — hanya benar-benar relevan untuk
 * pegawai Kantor Pusat (KC/KCP tidak punya konsep divisi), tapi
 * kolomnya generik supaya tidak perlu pengecualian office_type di skema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->uuid('supervisor_id')->nullable()->after('office_id');
            $table->string('division', 100)->nullable()->after('position_id');

            $table->foreign('supervisor_id')->references('id')->on('emp_employees');
        });
    }

    public function down(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn(['supervisor_id', 'division']);
        });
    }
};
