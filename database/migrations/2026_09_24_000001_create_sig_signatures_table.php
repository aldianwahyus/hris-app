<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda Tangan Elektronik INTERNAL — modul baru (evaluasi PM/client
 * 2026-09-02). SENGAJA BUKAN e-materai berkekuatan hukum UU ITE (itu
 * perlu penyedia berizin berbayar seperti Peruri/Privy/DigiSign, di
 * luar jangkauan pekerjaan ini, dikonfirmasi bersama user) — murni
 * tanda tangan organisasi internal: gambar (canvas)/nama diketik +
 * `document_hash` (sha256 atas identitas dokumen+penandatangan+waktu,
 * BUKAN byte PDF — lihat SignDocument, dokumen yang sudah digenerate
 * sebagai berkas terpisah seperti SK tidak bisa ditera ulang tanpa
 * pustaka manipulasi PDF yang di luar cakupan modul ini) sebagai jejak
 * anti-ubah, dicatat di jejak audit (AuditAction::Signed).
 *
 * `signable_type`+`signable_id` POLIMORFIK murni string (pola SAMA
 * `aud_change_logs.auditable_type/auditable_id` yang sudah ada, BUKAN
 * foreign key sungguhan) — satu mekanisme dipakai lintas jenis dokumen
 * (SK, Surat Layanan Mandiri, dst.) tanpa tabel terpisah per jenis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sig_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('signable_type', 50);
            $table->uuid('signable_id');
            $table->uuid('signer_employee_id');
            $table->string('signer_name_snapshot', 150);
            $table->string('signer_role_snapshot', 100)->nullable();
            $table->text('signature_image_base64')->nullable();
            $table->string('typed_name', 150)->nullable();
            $table->timestampTz('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('document_hash', 64);
            $table->timestampTz('created_at');

            $table->foreign('signer_employee_id')->references('id')->on('emp_employees')->restrictOnDelete();
            $table->index(['signable_type', 'signable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sig_signatures');
    }
};
