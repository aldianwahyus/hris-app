<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Office\Application\ImportOffices;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/**
 * Impor massal kantor dari CSV — pola PERSIS EmployeeImportController,
 * beda satu hal penting: md_offices TIDAK punya antrean persetujuan
 * (lihat ImportOffices), jadi baris yang berhasil LANGSUNG aktif, tidak
 * "menunggu persetujuan".
 */
final class OfficeImportController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ImportOffices $import,
    ) {}

    public function index(): View
    {
        return view('admin.office-import');
    }

    public function template(): Response
    {
        $header = ['kode', 'nama', 'tipe', 'zona_waktu', 'alamat', 'kelas', 'kode_kantor_induk'];
        // Kolom "alamat" SENGAJA tidak memakai koma di dalam nilainya —
        // baris ini ditulis lewat implode() polos (bukan fputcsv), jadi
        // koma di tengah nilai akan menggeser kolom-kolom setelahnya
        // (bug ditemukan lewat pengujian round-trip: alamat "Jl. X No.
        // 1, Bima" membuat "kelas" dan "kode_kantor_induk" ikut bergeser).
        $contoh = ['KC-BIMA', 'KC Bima', 'branch', 'Asia/Makassar', 'Jl. Sultan Hasanuddin No. 1 Bima', 'KC_1', ''];

        // BOM UTF-8 + baris "sep=," — pola SAMA PERSIS
        // EmployeeImportController::template() (tanpa ini Excel di
        // region Indonesia membaca titik koma, bukan koma).
        $csv = "\xEF\xBB\xBF"."sep=,\r\n".implode(',', $header)."\r\n".implode(',', $contoh)."\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contoh-impor-kantor.csv"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'berkas' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('berkas');
        $realPath = $file?->getRealPath();

        abort_if($realPath === false || $realPath === null, 422, 'Berkas gagal diunggah.');

        try {
            $result = $this->import->handle(
                filePath: $realPath,
                actor: new AuditActor(
                    actorId: $this->actor->employeeId(),
                    actorRole: implode(',', $this->actor->roles()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (RuntimeException $e) {
            return redirect()->route('sysadmin.offices.import.index')->with('gagal', $e->getMessage());
        }

        $pesan = "Impor selesai: {$result->imported} kantor tersimpan dan langsung aktif, {$result->skipped} baris dilewati.";

        if ($result->skipped > 0) {
            $pesan .= ' Rincian baris dilewati: '.implode(' ', array_slice($result->skippedReasons, 0, 5));
        }

        return redirect()->route('sysadmin.offices.import.index')
            ->with($result->imported > 0 ? 'sukses' : 'gagal', $pesan);
    }
}
