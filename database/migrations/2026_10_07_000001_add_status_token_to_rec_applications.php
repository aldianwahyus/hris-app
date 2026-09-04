<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perluasan Rekrutmen (Fase 2) — token portal status kandidat, pola
 * SAMA `rec_job_offers.response_token`. Kandidat membuka
 * `/lowongan/status/{token}` (PUBLIK) untuk memeriksa tahap
 * lamarannya, lihat PublicCareersController::statusPage().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rec_applications', function (Blueprint $table) {
            $table->string('status_token', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('rec_applications', function (Blueprint $table) {
            $table->dropColumn('status_token');
        });
    }
};
