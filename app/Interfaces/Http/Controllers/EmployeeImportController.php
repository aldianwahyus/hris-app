<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\ImportNewEmployeeRequests;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/**
 * Impor massal usulan pegawai baru dari CSV — pola PERSIS
 * AttendanceDeviceImportController. Jalur maker SAMA dengan
 * SystemAdminEmployeeController::store() (satu baris CSV = satu usulan
 * pending menunggu hr_approver), lihat ImportNewEmployeeRequests.
 */
final class EmployeeImportController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ImportNewEmployeeRequests $import,
    ) {}

    public function index(): View
    {
        return view('admin.sysadmin-employee-import');
    }

    public function template(): Response
    {
        $header = [
            'nrp', 'nama', 'tanggal_lahir', 'jenis_kelamin', 'email', 'tanggal_masuk', 'status_kepegawaian', 'kode_kantor', 'kode_jabatan', 'golongan', 'job_grade',
            'status_kawin', 'tanggungan', 'tanggal_tetap', 'nrp_atasan', 'divisi',
            'agama', 'nomor_ktp', 'nomor_npwp', 'bpjs_tenaga_kerja', 'bpjs_kesehatan',
            'nomor_simpeda', 'nomor_tambora_rencana', 'tmt_pangkat',
            'alamat', 'no_telepon', 'kontak_darurat_nama', 'kontak_darurat_hubungan',
            'kontak_darurat_telepon', 'pendidikan_terakhir', 'pendidikan_jurusan',
        ];
        $contoh = [
            '2026.01.0001', 'Contoh Nama', '1995-01-15', 'L', 'contoh@bankntbsyariah.demo', '2026-01-01', 'tetap', 'KC-MTR', 'TELLER', '5', '10',
            'menikah', '1', '2026-01-01', '', 'Divisi Operasional',
            'Islam', '3271234567890001', '12.345.678.9-012.000', '01234567890', '0001234567890',
            '', '', '2026-01-01',
            'Jl. Contoh No. 1, Mataram', '081234567890', 'Contoh Pasangan', 'Suami/Istri',
            '081298765432', 'S1', 'Manajemen',
        ];

        // BOM UTF-8 + baris "sep=," WAJIB — tanpa ini Excel membaca
        // delimiter memakai pengaturan region Windows (di Indonesia
        // biasanya titik koma, bukan koma), sehingga SELURUH baris
        // dobel ke kolom A alih-alih terpisah per kolom (dilaporkan
        // pengguna lewat tangkapan layar). "sep=," di baris PERTAMA
        // memaksa Excel selalu memakai koma, apa pun region-nya.
        $csv = "\xEF\xBB\xBF"."sep=,\r\n".implode(',', $header)."\r\n".implode(',', $contoh)."\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contoh-impor-pegawai.csv"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'berkas' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $actingEmployeeId = $this->actor->employeeId();
        abort_if($actingEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $file = $request->file('berkas');
        $realPath = $file?->getRealPath();

        abort_if($realPath === false || $realPath === null, 422, 'Berkas gagal diunggah.');

        try {
            $result = $this->import->handle(
                filePath: $realPath,
                requestedBy: $actingEmployeeId,
                actor: new AuditActor(
                    actorId: $actingEmployeeId,
                    actorRole: implode(',', $this->actor->roles()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (RuntimeException $e) {
            return redirect()->route('sysadmin.employees.import.index')->with('gagal', $e->getMessage());
        }

        $pesan = "Impor selesai: {$result->imported} baris terkirim (menunggu persetujuan hr_approver), {$result->skipped} baris dilewati.";

        if ($result->skipped > 0) {
            $pesan .= ' Rincian baris dilewati: '.implode(' ', array_slice($result->skippedReasons, 0, 5));
        }

        return redirect()->route('sysadmin.employees.import.index')
            ->with($result->imported > 0 ? 'sukses' : 'gagal', $pesan);
    }
}
