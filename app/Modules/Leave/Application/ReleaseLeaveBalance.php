<?php

declare(strict_types=1);

namespace App\Modules\Leave\Application;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mengembalikan hari cuti yang sudah terpotong saat pengajuan DITOLAK
 * (dipanggil LeaveApprovalQueueController) atau KEDALUWARSA (dipanggil
 * ProcessWorkflowSla lewat SQL mentah — Shared tidak boleh bergantung
 * pada modul bisnis, lihat komentar di sana — kelas ini HANYA untuk
 * jalur reject yang boleh memakai Application layer Leave).
 *
 * Membalik PERSIS rencana debit yang disimpan SubmitLeaveRequest
 * (leave_requests.bucket_debits) — bukan menghitung ulang dari saldo
 * saat ini, yang bisa saja sudah berubah sejak pengajuan (penyesuaian
 * admin, tahun baru, dst).
 */
final class ReleaseLeaveBalance
{
    public function handle(string $leaveRequestId): void
    {
        DB::transaction(function () use ($leaveRequestId) {
            $request = DB::table('leave_requests')->where('id', $leaveRequestId)->lockForUpdate()->first();

            if ($request === null || $request->bucket_debits === null) {
                return; // sudah dilepas sebelumnya, atau pengajuan lama tanpa snapshot — tidak ada yang bisa dibalik.
            }

            $year = (int) (new DateTimeImmutable((string) $request->start_date))->format('Y');
            $debits = json_decode((string) $request->bucket_debits, true) ?? [];

            foreach ($debits as $debit) {
                DB::table('leave_balances')
                    ->where('employee_id', $request->employee_id)
                    ->where('year', $year)
                    ->where('bucket_type', $debit['bucket_type'])
                    ->decrement('used_days', (float) $debit['days']);
            }

            // NULL-kan setelah dilepas — bukti sudah dibalik, dan mencegah
            // pelepasan dua kali kalau method ini terpanggil ulang untuk
            // pengajuan yang sama.
            DB::table('leave_requests')->where('id', $leaveRequestId)->update(['bucket_debits' => null]);
        });
    }
}
