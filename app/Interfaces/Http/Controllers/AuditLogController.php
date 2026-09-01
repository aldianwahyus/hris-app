<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\CsvExport;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Log audit bank-wide — SEBELUMNYA hanya Auditor (§6.3), SEKARANG
 * juga system_admin + hr_approver (permission audit-log.view, lihat
 * migrasi 2026_09_18_000002) atas permintaan akses Dashboard/Audit
 * Trail untuk SYSADMIN & Admin HC. Independensi Auditor TIDAK
 * berkurang — dia tetap hanya-baca di sini seperti sebelumnya, hanya
 * sekarang berbagi akses BACA dengan dua peran lain (bukan exclusive).
 *
 * Filter modul/aktor/tanggal + paginasi asli (BUKAN lagi limit(100)
 * hardcode tanpa cara mempersempit) — old_values/new_values (SUDAH
 * ada di skema, sebelumnya tidak pernah di-select/ditampilkan) kini
 * disertakan untuk diff lama/baru per baris. TIDAK ADA aksi ubah/hapus
 * di layar ini maupun di baliknya — AuditRepository tidak pernah
 * mengekspos operasi itu (append-only, DB-001 §3.1).
 */
final class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $filters = $this->resolveFilters($request);

        return view('admin.audit-log', [
            'entries' => $this->filteredQuery($filters)->paginate(self::PER_PAGE)->withQueryString(),
            'modules' => $this->distinctModules(),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);

        return CsvExport::download(
            'log-audit-'.now()->format('Y-m-d-His').'.csv',
            ['Waktu', 'Aktor', 'NRP', 'Peran', 'Tipe Objek', 'ID Objek', 'Aksi', 'Referensi'],
            $this->filteredQuery($filters)->get()->map(fn ($e) => [
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

    /** @return array{modul: ?string, aktor: ?string, dari: ?DateTimeImmutable, sampai: ?DateTimeImmutable} */
    private function resolveFilters(Request $request): array
    {
        $modul = $request->string('modul')->toString();
        $aktor = trim($request->string('aktor')->toString());

        return [
            'modul' => $modul !== '' ? $modul : null,
            'aktor' => $aktor !== '' ? $aktor : null,
            'dari' => DateTimeImmutable::createFromFormat('!Y-m-d', $request->string('dari')->toString()) ?: null,
            'sampai' => DateTimeImmutable::createFromFormat('!Y-m-d', $request->string('sampai')->toString()) ?: null,
        ];
    }

    /** @param array{modul: ?string, aktor: ?string, dari: ?DateTimeImmutable, sampai: ?DateTimeImmutable} $filters */
    private function filteredQuery(array $filters): Builder
    {
        $query = DB::table('aud_change_logs as a')
            ->leftJoin('emp_employees as e', 'e.id', '=', 'a.actor_id')
            ->select(
                'a.id', 'a.occurred_at', 'a.actor_role', 'a.auditable_type',
                'a.auditable_id', 'a.action', 'a.context_ref', 'a.old_values', 'a.new_values',
                'e.full_name as actor_name', 'e.nrp as actor_nrp'
            )
            ->orderByDesc('a.occurred_at');

        if ($filters['modul'] !== null) {
            $query->where('a.auditable_type', $filters['modul']);
        }

        if ($filters['aktor'] !== null) {
            $needle = '%'.$filters['aktor'].'%';
            $query->where(function (Builder $q) use ($needle) {
                $q->where('e.full_name', 'like', $needle)->orWhere('e.nrp', 'like', $needle);
            });
        }

        if ($filters['dari'] !== null) {
            $query->where('a.occurred_at', '>=', $filters['dari']->format('Y-m-d').' 00:00:00');
        }

        if ($filters['sampai'] !== null) {
            $query->where('a.occurred_at', '<=', $filters['sampai']->format('Y-m-d').' 23:59:59');
        }

        return $query;
    }

    /** @return Collection<int, string> */
    private function distinctModules(): Collection
    {
        return DB::table('aud_change_logs')
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type');
    }
}
