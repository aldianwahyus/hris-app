<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2FA (TOTP) — Fase 2 (evaluasi PM/client 2026-09-03). Kolom pola PERSIS
 * Laravel Fortify supaya dikenali developer lain di kemudian hari —
 * `two_factor_secret`/`two_factor_recovery_codes` TERENKRIPSI (cast
 * `encrypted`/`encrypted:array` di model User, bukan kolom biasa),
 * `two_factor_confirmed_at` NULL berarti belum pernah menuntaskan setup
 * (secret bisa saja sudah ditulis sementara tapi belum dikonfirmasi —
 * lihat SetupTwoFactor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestampTz('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
