<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Identitas perusahaan (nama+lambang) untuk header dokumen cetak
 * (DomPDF) — Memo Internal, Nota Debet, Jurnal Slip, Surat Keterangan,
 * dst. Dibaca dari `company_settings` (dikelola SYSADMIN lewat
 * CompanySettingsController) SUPAYA perubahan nama/lambang perusahaan
 * TIDAK PERNAH butuh perubahan kode — satu-satunya cara yang benar
 * menangani perubahan identitas korporat di lingkungan yang bisa
 * berubah (merger, rebranding, dst.).
 *
 * `logo_path` NULL (belum pernah diunggah admin) ATAU gagal dibaca
 * (mis. objek terhapus dari storage) jatuh ke lambang BAWAAN aplikasi
 * (berkas lokal) — dokumen resmi tidak boleh gagal dirender hanya
 * karena storage S3/MinIO sedang bermasalah.
 *
 * SENGAJA TANPA cache statis (BEDA dari CompanyLogo versi sebelumnya,
 * yang gambarnya sungguh tidak pernah berubah dalam satu proses) —
 * nilai di sini BISA berubah kapan pun admin menyimpan pengaturan baru,
 * jadi cache statis akan menyimpan nilai basi selama proses itu hidup
 * (nyata terlihat di rangkaian test yang mengubah lalu langsung
 * membaca ulang pengaturan). Satu query indeks-primer per pemanggilan
 * cukup murah untuk dilakukan tanpa cache.
 */
final class CompanyProfile
{
    private const FALLBACK_NAME = 'PT Bank NTB Syariah';

    private const FALLBACK_LOGO_PATH = 'images/logo_ntbs-BSIF94NC.png';

    public static function name(): string
    {
        return self::settings()->company_name ?? self::FALLBACK_NAME;
    }

    public static function logoDataUri(): string
    {
        $logoPath = self::settings()->logo_path ?? null;

        if ($logoPath !== null) {
            try {
                $contents = Storage::disk('s3')->get($logoPath);

                if ($contents !== null) {
                    $mime = Storage::disk('s3')->mimeType($logoPath) ?: 'image/png';

                    return "data:{$mime};base64,".base64_encode($contents);
                }
            } catch (Throwable $e) {
                Log::warning('Gagal membaca lambang perusahaan kustom, jatuh ke lambang bawaan.', ['exception' => $e->getMessage()]);
            }
        }

        return self::fallbackLogoDataUri();
    }

    private static function fallbackLogoDataUri(): string
    {
        $contents = file_get_contents(public_path(self::FALLBACK_LOGO_PATH));

        if ($contents === false) {
            throw new RuntimeException('Berkas lambang perusahaan bawaan tidak ditemukan.');
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    private static function settings(): object
    {
        return DB::table('company_settings')->orderBy('created_at')->first()
            ?? (object) ['company_name' => self::FALLBACK_NAME, 'logo_path' => null];
    }
}
