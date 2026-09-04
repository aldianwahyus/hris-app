<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Application;

use Illuminate\Support\Facades\DB;

/**
 * "Unduh Data Saya" (UU PDP, Fase 2) — mengumpulkan data inti milik
 * SATU pegawai jadi satu struktur portabel (JSON, hak portabilitas
 * data). Lingkup SENGAJA dibatasi ke data yang benar-benar "milik"
 * pegawai itu (profil + riwayat pengajuan sendiri) — catatan internal
 * HC (mis. `hd_ticket_replies.is_internal_note`) TIDAK disertakan,
 * itu bukan data pegawai, itu catatan kerja HC TENTANG pegawai.
 */
final class ExportEmployeeData
{
    /** @return array<string, mixed> */
    public function handle(string $employeeId): array
    {
        $profile = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $employeeId)
            ->select('e.nrp', 'e.full_name', 'e.email', 'e.no_telepon', 'e.alamat', 'e.join_date', 'e.employment_status', 'o.name as office_name')
            ->first();

        return [
            'diekspor_pada' => now()->toIso8601String(),
            'profil' => $profile,
            'riwayat_cuti' => DB::table('leave_requests')
                ->where('employee_id', $employeeId)
                ->select('request_number', 'leave_type', 'start_date', 'end_date', 'total_days', 'status', 'reason', 'created_at')
                ->get(),
            'riwayat_izin' => DB::table('izin_requests')
                ->where('employee_id', $employeeId)
                ->select('category', 'start_date', 'end_date', 'reason', 'status', 'created_at')
                ->get(),
            'riwayat_dokumen_mandiri' => DB::table('doc_requests')
                ->where('employee_id', $employeeId)
                ->select('document_type', 'purpose', 'status', 'created_at')
                ->get(),
            'riwayat_tiket_bantuan' => DB::table('hd_tickets')
                ->where('employee_id', $employeeId)
                ->select('ticket_number', 'category', 'subject', 'status', 'created_at')
                ->get(),
        ];
    }
}
