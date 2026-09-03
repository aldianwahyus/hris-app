<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding — modul baru (evaluasi PM/client 2026-09-02). Kolom
 * TIDAK NULL berarti pegawai masih aktif — TIDAK mengubah enum
 * employment_status yang sudah ada (non-destruktif). Diisi oleh
 * MarkSeparationComplete setelah seluruh item clearance selesai;
 * dicek oleh AuthenticateEmployee untuk menolak login pegawai yang
 * sudah keluar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->timestampTz('separated_at')->nullable()->after('employment_status');
        });
    }

    public function down(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->dropColumn('separated_at');
        });
    }
};
