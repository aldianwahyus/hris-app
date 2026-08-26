<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluasi Pelatihan Level 1-4 penuh (BRD §5.5, Kirkpatrick):
 * - Level 1 (Kepuasan peserta): satisfaction_score/comments, diisi
 *   PEGAWAI sendiri (lihat SubmitTrainingEvaluation).
 * - Level 2 (Peningkatan knowledge): PAKAI ULANG Assessment Center
 *   (Phase 2) lewat kolom `evaluation_type` baru di lms_assessments
 *   (pre_test/post_test/general) — TIDAK ada mesin ujian baru.
 * - Level 3 (Perubahan perilaku kerja): behavior_score/comments,
 *   diisi HC (bukan atasan langsung pegawai itu sendiri — simplifikasi
 *   yang disengaja, lihat plan).
 * - Level 4 (Dampak terhadap kinerja): impact_notes — SENGAJA
 *   kualitatif (teks bebas), BUKAN metrik KPI numerik. App ini tidak
 *   punya sistem KPI/penilaian kinerja terukur sama sekali (alasan
 *   sama seperti ROI Pelatihan tidak dibangun di Phase 3 Analytics) —
 *   mengarang angka KPI akan jadi data palsu yang menyesatkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_training_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('enrollment_id')->unique();
            $table->unsignedTinyInteger('satisfaction_score')->nullable();
            $table->text('satisfaction_comments')->nullable();
            $table->unsignedTinyInteger('behavior_score')->nullable();
            $table->text('behavior_comments')->nullable();
            $table->uuid('behavior_assessed_by')->nullable();
            $table->timestampTz('behavior_assessed_at')->nullable();
            $table->text('impact_notes')->nullable();
            $table->uuid('impact_assessed_by')->nullable();
            $table->timestampTz('impact_assessed_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('enrollment_id')->references('id')->on('lms_enrollments')->cascadeOnDelete();
        });

        Schema::table('lms_assessments', function (Blueprint $table) {
            $table->string('evaluation_type', 20)->default('general')->after('course_id'); // pre_test | post_test | general
        });
    }

    public function down(): void
    {
        Schema::table('lms_assessments', function (Blueprint $table) {
            $table->dropColumn('evaluation_type');
        });

        Schema::dropIfExists('lms_training_evaluations');
    }
};
