<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layanan Dokumen Mandiri — modul baru (evaluasi PM/client 2026-09-02).
 * SATU tahap (pola PERSIS Izin/Tukar Shift) — HC langsung terbitkan
 * atau tolak, tidak ada tahap berjenjang seperti Cuti/Lembur/SPPD.
 * TIDAK ADA kolom berkas PDF tersimpan — dokumen di-render ULANG
 * on-demand dari baris ini + data pegawai (pola SAMA SPKL/Slip Gaji,
 * BUKAN diunggah seperti SK), tanda tangan (lihat sig_signatures,
 * signable_type='document_request') MELENGKAPI dokumen, bukan gerbang
 * wajib sebelum bisa diunduh — pegawai tetap bisa mengunduh versi
 * "belum ditandatangani" begitu status='siap'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('document_type', 50); // surat_keterangan_kerja | surat_referensi | surat_keterangan_penghasilan | lainnya
            $table->text('purpose');
            $table->string('status', 20)->default('pending'); // pending | siap | ditolak
            $table->uuid('processed_by')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->foreign('processed_by')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_requests');
    }
};
