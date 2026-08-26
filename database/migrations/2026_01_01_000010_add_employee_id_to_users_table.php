<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan akun login → data pegawai (ARCH-001 §6.2, DEC-02).
 *
 * Sanctum adalah identity provider tunggal: satu baris `users` per
 * pegawai yang berhak masuk. NRP dipakai sebagai kredensial login,
 * namun tetap milik `emp_employees` — `users` hanya menaut ke sana,
 * bukan menduplikasi datanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('employee_id')->nullable()->unique()->after('id');
            $table->foreign('employee_id')->references('id')->on('emp_employees');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }
};
