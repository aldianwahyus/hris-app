<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\CsvExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Log audit bank-wide untuk Auditor — lingkup BANK_WIDE, hanya-baca,
 * independen dari peran operasional lain (§6.3).
 *
 * Menampilkan 100 entri terbaru dari aud_change_logs (append-only,
 * DB-001 §3.1). Tidak ada aksi ubah/hapus di layar ini maupun di
 * baliknya — AuditRepository tidak pernah mengekspos operasi itu.
 */
final class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('admin.audit-log', ['entries' => $this->entries()]);
    }

    public function exportCsv(): StreamedResponse
    {
        return CsvExport::download(
            'log-audit-'.now()->format('Y-m-d-His').'.csv',
            ['Waktu', 'Aktor', 'NRP', 'Peran', 'Tipe Objek', 'ID Objek', 'Aksi', 'Referensi'],
            $this->entries()->map(fn ($e) => [
                $e->occurred_at,
                $e->actor_name ?? '(sistem)',
                $e->actor_nrp ?? '',
                $e->actor_role,
                $e->auditable_type,
                $e->auditable_id,
                $e->action,
                $e->context_ref ?? '',
            ])->all(),
        );
    }

    /** @return Collection<int, stdClass> */
    private function entries()
    {
        return DB::table('aud_change_logs as a')
            ->leftJoin('emp_employees as e', 'e.id', '=', 'a.actor_id')
            ->select(
                'a.id', 'a.occurred_at', 'a.actor_role', 'a.auditable_type',
                'a.auditable_id', 'a.action', 'a.context_ref',
                'e.full_name as actor_name', 'e.nrp as actor_nrp'
            )
            ->orderByDesc('a.occurred_at')
            ->limit(100)
            ->get();
    }
}
