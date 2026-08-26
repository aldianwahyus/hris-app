<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan pengajuan cuti/lembur ke instansi Workflow Engine yang
 * memutuskannya (Tahap 2 — Pengajuan dari Layar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->uuid('wf_instance_id')->nullable()->after('id');
            $table->foreign('wf_instance_id')->references('id')->on('wf_instances');
        });

        Schema::table('ovt_requests', function (Blueprint $table) {
            $table->uuid('wf_instance_id')->nullable()->after('id');
            $table->foreign('wf_instance_id')->references('id')->on('wf_instances');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['wf_instance_id']);
            $table->dropColumn('wf_instance_id');
        });

        Schema::table('ovt_requests', function (Blueprint $table) {
            $table->dropForeign(['wf_instance_id']);
            $table->dropColumn('wf_instance_id');
        });
    }
};
