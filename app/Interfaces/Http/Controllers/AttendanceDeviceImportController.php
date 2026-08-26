<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Attendance\Application\ImportAttendanceDeviceScans;
use App\Modules\Attendance\Application\SyncDeviceAttendance;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Impor ekspor mesin fingerprint (SYSADMIN, TOR tambahan) — mengisi
 * att_device_scan_logs dari berkas CSV/TXT, lalu LANGSUNG memicu
 * App\Modules\Attendance\Application\SyncDeviceAttendance yang SUDAH
 * ADA (reuse penuh, bukan menunggu jadwal `absensi:sinkronkan-mesin`).
 * Lingkup TEKNIS (device/mesin), sama seperti SystemAdminUserController.
 */
final class AttendanceDeviceImportController extends Controller
{
    public function __construct(
        private readonly ImportAttendanceDeviceScans $import,
        private readonly SyncDeviceAttendance $sync,
    ) {}

    public function index(): View
    {
        $offices = DB::table('md_offices')->orderBy('name')->get(['id', 'name']);

        $recentLogs = DB::table('att_device_scan_logs')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $unmatchedPins = DB::table('att_device_scan_logs')
            ->whereNotNull('processed_at')
            ->whereNull('employee_id')
            ->selectRaw('device_pin, count(*) as jumlah, max(scanned_at) as terakhir')
            ->groupBy('device_pin')
            ->orderByDesc('terakhir')
            ->limit(20)
            ->get();

        return view('admin.attendance-device-import', compact('offices', 'recentLogs', 'unmatchedPins'));
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'office_id' => ['required', 'uuid', 'exists:md_offices,id'],
            'berkas' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('berkas');
        $realPath = $file?->getRealPath();

        abort_if($realPath === false || $realPath === null, 422, 'Berkas gagal diunggah.');

        try {
            $importResult = $this->import->handle(
                filePath: $realPath,
                officeId: $validated['office_id'],
            );

            // SENGAJA di dalam try yang sama: sinkronisasi (termasuk
            // ParameterNotFoundException dari ambang keterlambatan/jam
            // kerja) juga wajib gagal secara manusiawi, bukan 500 mentah.
            $syncResult = $this->sync->handle((new DateTimeImmutable)->modify('-2 days'));
        } catch (RuntimeException $e) {
            return redirect()->route('sysadmin.attendance-device.index')->with('gagal', $e->getMessage());
        }

        $pesan = "Impor selesai: {$importResult->imported} baris masuk, {$importResult->skipped} baris dilewati. "
            ."Sinkronisasi: {$syncResult->matched} cocok, {$syncResult->unmatched} pin tidak dikenal.";

        if ($importResult->skipped > 0) {
            $pesan .= ' Rincian baris dilewati: '.implode(' ', array_slice($importResult->skippedReasons, 0, 5));
        }

        return redirect()->route('sysadmin.attendance-device.index')->with('sukses', $pesan);
    }
}
