<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cegah pegawai punya lebih dari SATU pengajuan lembur "hidup" (bukan
 * ditolak/kedaluwarsa) untuk tanggal kerja yang sama — akar masalah
 * double-payment: dua SPKL terpisah untuk hari yang sama, keduanya
 * disetujui, keduanya bisa lolos pencairan karena guard lama
 * (ProcessOvertimePaymentBatch) hanya mengecek per ovt_request_id,
 * bukan per (employee_id, work_date).
 *
 * Partial unique index (BUKAN unique index biasa) — 'rejected' dan
 * 'expired' SENGAJA dikecualikan supaya pegawai TETAP bisa mengajukan
 * ulang lembur di tanggal yang sama setelah pengajuan sebelumnya
 * ditolak/kedaluwarsa (pola sama SubmitLeaveRequest::isFirstTakenThisYear
 * yang juga mengecualikan status non-final serupa). deleted_at IS NULL
 * disertakan karena ovt_requests pakai soft-delete.
 *
 * Laravel Schema Builder tidak punya API fluent untuk index parsial —
 * dibuat lewat DB::statement() mentah, pola umum Postgres untuk kasus
 * ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ovt_requests_employee_workdate_unique
            ON ovt_requests (employee_id, work_date)
            WHERE status NOT IN ('rejected', 'expired') AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ovt_requests_employee_workdate_unique');
    }
};
