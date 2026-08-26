<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV generik dipakai seluruh halaman laporan/rekap (Rekap
 * Biaya Lembur, Rekap Absensi, Log Audit, laporan LMS, dst) — SATU
 * sumber kebenaran pola ekspor, bukan fputcsv diulang di tiap
 * controller. Pola stream (bukan buffer penuh di memori) sama seperti
 * CSV template impor yang sudah ada (EmployeeImportController,
 * GeneratePayslipDeductionTemplate) — cuma dibalik arahnya (ekspor,
 * bukan template kosong untuk diisi).
 */
final class CsvExport
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                throw new RuntimeException('Tidak dapat membuka php://output untuk ekspor CSV.');
            }

            // BOM UTF-8 — supaya karakter non-ASCII (mis. "Σ", nama
            // pegawai) tidak berantakan saat dibuka Excel Windows,
            // yang defaultnya membaca CSV sebagai ANSI tanpa BOM.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
