<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengisi att_device_scan_logs (tabel staging "kotak masuk" — lihat
 * Infrastructure/StubAttendanceDeviceGateway) dari berkas CSV/TXT
 * hasil ekspor mesin fingerprint. TIDAK menyentuh SyncDeviceAttendance
 * atau AttendanceDeviceGateway — begitu tabel ini terisi, alur
 * sinkronisasi yang SUDAH ADA memprosesnya tanpa perubahan apa pun.
 *
 * Header kolom fleksibel (case-insensitive): "pin"/"PIN" dan
 * "waktu"/"scanned_at"/"datetime" — pola ekspor umum mesin fingerprint
 * tanpa jaringan (ZKTeco/Fingerspot/Solution X100).
 *
 * Mesin mencatat jam DINDING setempat, TANPA info zona waktu — harus
 * ditafsirkan terhadap zona kantor tempat mesin itu terpasang ($officeId)
 * sebelum dikonversi ke UTC untuk disimpan (timestamptz), sejalan
 * dengan catatan konversi zona yang sama di SyncDeviceAttendance
 * (bank beroperasi lintas zona waktu — KC Surabaya WIB, sisanya WITA).
 *
 * Baris yang gagal diparse (pin kosong, format waktu tidak dikenali)
 * DILEWATI dan dihitung — TIDAK menggagalkan seluruh impor, karena
 * satu baris rusak di tengah ribuan baris ekspor mesin bukan alasan
 * membuang seluruhnya.
 */
final class ImportAttendanceDeviceScans
{
    public function handle(string $filePath, string $officeId): ImportResult
    {
        $office = DB::table('md_offices')->where('id', $officeId)->first();

        if ($office === null) {
            throw new RuntimeException("Kantor (id: {$officeId}) tidak ditemukan.");
        }

        $timezone = new DateTimeZone($office->timezone);

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Berkas tidak dapat dibaca: {$filePath}");
        }

        $imported = 0;
        $skipped = 0;
        $skippedReasons = [];

        try {
            $this->skipExcelSepDirective($handle);

            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('Berkas kosong atau bukan CSV yang valid.');
            }

            // Berkas ini murni ekspor mesin (bukan template yang kami
            // buat), tapi kalau pengguna sempat membukanya di Excel dulu
            // untuk memeriksa isi, baris "sep=," / BOM UTF-8 bisa saja
            // ikut tersimpan — dijaga di sini juga supaya tetap terbaca.
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            }

            $columns = $this->mapHeader($header);
            $now = new DateTimeImmutable;
            $rows = [];
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($row === [null]) {
                    continue; // baris kosong di akhir berkas
                }

                $pin = trim((string) ($row[$columns['pin']] ?? ''));
                $rawTime = trim((string) ($row[$columns['waktu']] ?? ''));

                if ($pin === '') {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: PIN kosong.";

                    continue;
                }

                try {
                    $scannedAtLocal = new DateTimeImmutable($rawTime, $timezone);
                } catch (Exception) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: format waktu tidak dikenali (\"{$rawTime}\").";

                    continue;
                }

                $rows[] = [
                    'id' => (string) Uuid7::generate(),
                    'device_pin' => $pin,
                    'scanned_at' => $scannedAtLocal->setTimezone(new DateTimeZone('UTC')),
                    'employee_id' => null,
                    'processed_at' => null,
                    'raw_payload' => json_encode(['baris' => $lineNumber, 'pin' => $pin, 'waktu' => $rawTime]),
                    'created_at' => $now,
                ];
                $imported++;
            }
        } finally {
            fclose($handle);
        }

        if ($rows !== []) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('att_device_scan_logs')->insert($chunk);
            }
        }

        return new ImportResult(imported: $imported, skipped: $skipped, skippedReasons: $skippedReasons);
    }

    /** @param  resource  $handle */
    private function skipExcelSepDirective(mixed $handle): void
    {
        $position = ftell($handle);
        $firstLine = fgets($handle);

        if ($firstLine !== false) {
            $withoutBom = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;

            if (preg_match('/^sep=.\s*$/i', trim($withoutBom)) === 1) {
                return;
            }
        }

        fseek($handle, $position === false ? 0 : $position);
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array{pin: int, waktu: int}
     */
    private function mapHeader(array $header): array
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $pinIndex = array_search('pin', $normalized, true);
        $waktuIndex = array_search('waktu', $normalized, true);
        $waktuIndex = $waktuIndex !== false ? $waktuIndex : array_search('scanned_at', $normalized, true);
        $waktuIndex = $waktuIndex !== false ? $waktuIndex : array_search('datetime', $normalized, true);

        if ($pinIndex === false || $waktuIndex === false) {
            throw new RuntimeException(
                'Header berkas harus memuat kolom "pin" dan "waktu" (atau "scanned_at"/"datetime") — '
                .'kolom ditemukan: '.implode(', ', $normalized)
            );
        }

        return ['pin' => $pinIndex, 'waktu' => $waktuIndex];
    }
}
