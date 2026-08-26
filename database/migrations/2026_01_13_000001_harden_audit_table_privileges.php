<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEC-2026-08: 2026_01_01_000001_create_audit_tables.php mengklaim
 * sifat append-only "ditegakkan pada tingkat hak akses basis data
 * (REVOKE UPDATE, DELETE)" — TAPI tidak pernah benar-benar menjalankan
 * REVOKE apa pun. Klaim yang tidak ditegakkan lebih berbahaya daripada
 * ketiadaan proteksi: siapa pun dengan akses SQL langsung mengira
 * tabel ini sudah dilindungi, padahal tidak.
 *
 * CATATAN PENTING (batasan yang jujur, bukan solusi penuh): REVOKE di
 * bawah ini efektif HANYA bila koneksi aplikasi (DB_USERNAME) BUKAN
 * pemilik (owner) tabel — di PostgreSQL, owner tabel SELALU bisa
 * UPDATE/DELETE terlepas dari REVOKE apa pun terhadap dirinya sendiri
 * (ACL tidak berlaku bagi owner). Migrasi ini berjalan sebagai role
 * yang SAMA dengan koneksi aplikasi (satu DB_USERNAME untuk migrasi
 * maupun runtime, per konfigurasi saat ini) — bila role itu juga
 * owner tabel (kemungkinan besar benar hari ini), REVOKE ini menjadi
 * TIDAK BEROPERASI (no-op) terhadap koneksi aplikasi itu sendiri.
 *
 * Proteksi SUNGGUHAN di tingkat basis data membutuhkan DUA role
 * terpisah: satu role admin/migrasi yang memiliki (owns) tabel, dan
 * satu role runtime aplikasi terpisah yang HANYA diberi INSERT+SELECT
 * — ini keputusan topologi deployment (bukan sesuatu yang aman
 * ditebak/dipaksakan dari satu migrasi Laravel tanpa koordinasi
 * infrastruktur). Dicatat sebagai risiko produksi yang tersisa.
 *
 * REVOKE tetap dijalankan di sini sebagai pertahanan berlapis
 * best-effort — tidak merugikan bila menjadi no-op (owner tetap bisa
 * INSERT/SELECT seperti biasa, satu-satunya operasi yang dipakai
 * AuditRepository), dan LANGSUNG efektif begitu topologi produksi
 * benar-benar memisahkan role migrasi vs role runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('REVOKE UPDATE, DELETE ON aud_change_logs FROM PUBLIC;');
        DB::statement('REVOKE UPDATE, DELETE ON aud_access_logs FROM PUBLIC;');
    }

    public function down(): void
    {
        DB::statement('GRANT UPDATE, DELETE ON aud_change_logs TO PUBLIC;');
        DB::statement('GRANT UPDATE, DELETE ON aud_access_logs TO PUBLIC;');
    }
};
