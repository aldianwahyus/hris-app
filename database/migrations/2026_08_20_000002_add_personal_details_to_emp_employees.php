<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "CV Saya" (ESS, pegawai) — data PRIBADI murni, BUKAN data organisasi
 * (jabatan/kantor/grade tetap hanya lewat maker-checker hr_admin/
 * SYSADMIN, lihat EditableEmployeeField). Field ini diedit LANGSUNG
 * oleh pegawai sendiri tanpa persetujuan — lihat SelfEditableEmployeeField.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->text('alamat')->nullable()->after('email');
            $table->string('no_telepon', 20)->nullable()->after('alamat');
            $table->string('kontak_darurat_nama', 150)->nullable()->after('no_telepon');
            $table->string('kontak_darurat_hubungan', 50)->nullable()->after('kontak_darurat_nama');
            $table->string('kontak_darurat_telepon', 20)->nullable()->after('kontak_darurat_hubungan');
            $table->string('pendidikan_terakhir', 30)->nullable()->after('kontak_darurat_telepon');
            $table->string('pendidikan_jurusan', 100)->nullable()->after('pendidikan_terakhir');
        });
    }

    public function down(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            $table->dropColumn([
                'alamat', 'no_telepon', 'kontak_darurat_nama', 'kontak_darurat_hubungan',
                'kontak_darurat_telepon', 'pendidikan_terakhir', 'pendidikan_jurusan',
            ]);
        });
    }
};
