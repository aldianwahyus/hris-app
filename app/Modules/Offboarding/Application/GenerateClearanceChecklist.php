<?php

declare(strict_types=1);

namespace App\Modules\Offboarding\Application;

use App\Core\Domain\Uuid7;
use Illuminate\Support\Facades\DB;

/**
 * Membangkitkan checklist clearance saat satu pengajuan pemisahan
 * disetujui — item standar DITAMBAH satu item PER baris
 * `ast_assignments` aktif (returned_at IS NULL) milik pegawai itu
 * ("Kembalikan: {nama aset}"), dibaca LANGSUNG dari tabel Modul 1
 * Manajemen Aset via DB::table() (bukan impor App\Modules\Asset\*,
 * yang dilarang ModuleBoundaryTest M-1 — baca lintas tabel murni
 * TANPA menyentuh kelas modul lain adalah pola yang sudah dipakai di
 * seluruh laporan lintas modul dalam basis kode ini, mis.
 * IncomeRecapController).
 */
final class GenerateClearanceChecklist
{
    /** @var array<int, array{item_name: string, category: string}> */
    private const STANDARD_ITEMS = [
        ['item_name' => 'Serah terima pekerjaan ke atasan/pengganti', 'category' => 'hc'],
        ['item_name' => 'Cabut akses sistem HRIS/aplikasi internal', 'category' => 'it'],
        ['item_name' => 'Kembalikan kartu identitas & akses kantor', 'category' => 'hc'],
        ['item_name' => 'Klarifikasi kewajiban keuangan (pinjaman/uang muka)', 'category' => 'keuangan'],
    ];

    public function handle(string $separationId, string $employeeId): void
    {
        DB::transaction(function () use ($separationId, $employeeId) {
            foreach (self::STANDARD_ITEMS as $item) {
                $this->insertItem($separationId, $item['item_name'], $item['category']);
            }

            $activeAssets = DB::table('ast_assignments as a')
                ->join('ast_assets as t', 't.id', '=', 'a.asset_id')
                ->where('a.employee_id', $employeeId)
                ->whereNull('a.returned_at')
                ->select('t.name')
                ->get();

            foreach ($activeAssets as $asset) {
                $this->insertItem($separationId, "Kembalikan: {$asset->name}", 'aset');
            }
        });
    }

    private function insertItem(string $separationId, string $itemName, string $category): void
    {
        DB::table('off_clearance_items')->insert([
            'id' => (string) Uuid7::generate(),
            'separation_id' => $separationId,
            'item_name' => $itemName,
            'category' => $category,
            'is_done' => false,
            'done_by' => null,
            'done_at' => null,
        ]);
    }
}
