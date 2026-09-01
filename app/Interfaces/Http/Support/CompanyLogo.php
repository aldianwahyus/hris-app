<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Support;

use RuntimeException;

/**
 * Lambang PT Bank NTB Syariah untuk header dokumen cetak (DomPDF) —
 * BUKAN dipakai untuk halaman biasa (yang sudah pakai asset() langsung,
 * lihat layouts/app.blade.php), karena DomPDF tidak mengambil aset lewat
 * asset()/URL relatif secara andal saat merender di server. Data URI
 * base64 dibaca sekali per proses dan disimpan statis — beberapa
 * dokumen bisa dirender dalam satu permintaan yang sama (mis. pratinjau
 * modal memuat 2-3 dokumen berurutan).
 *
 * SENGAJA TETAP logo Bank NTB Syariah asli (bukan lambang HCIS baru
 * yang dipakai layar login/sidebar) — dokumen resmi cetak (Memo
 * Internal, Nota Debet, Jurnal Slip, dst.) harus tetap memakai lambang
 * korporat resmi sesuai instruksi eksplisit pengguna, terlepas dari
 * lambang HCIS yang sudah diterapkan di permukaan web/mobile lain.
 */
final class CompanyLogo
{
    private static ?string $dataUri = null;

    public static function dataUri(): string
    {
        if (self::$dataUri === null) {
            $contents = file_get_contents(public_path('images/logo_ntbs-BSIF94NC.png'));

            if ($contents === false) {
                throw new RuntimeException('Berkas lambang perusahaan tidak ditemukan.');
            }

            self::$dataUri = 'data:image/png;base64,'.base64_encode($contents);
        }

        return self::$dataUri;
    }
}
