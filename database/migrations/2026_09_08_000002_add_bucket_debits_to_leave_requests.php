<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG DITEMUKAN lewat audit kode: SubmitLeaveRequest mendebit
 * leave_balances.used_days SAAT PENGAJUAN (mencegah pemesanan ganda),
 * tapi rencana debit (kantong mana, berapa hari — hasil
 * LeaveBalanceLedger::planConsumption()) tidak pernah disimpan di mana
 * pun. Akibatnya saat pengajuan DITOLAK atau KEDALUWARSA (SLA), tidak
 * ada cara mengembalikan hari yang sudah terpotong — pegawai kehilangan
 * jatah cuti secara PERMANEN meski pengajuannya tidak pernah disetujui.
 * Kolom ini menyimpan snapshot rencana debit per pengajuan supaya bisa
 * dibalik persis (lihat ReleaseLeaveBalance & ProcessWorkflowSla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->json('bucket_debits')->nullable()->after('total_days');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('bucket_debits');
        });
    }
};
