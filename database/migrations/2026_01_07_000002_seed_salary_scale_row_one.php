<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lampiran II (Tabel Skala Imbalan Kerja) BPP/137/03/64/2026 —
 * SENGAJA HANYA BARIS 1.
 *
 * Tabel aslinya berisi 35 baris × 19 Person Grade (665 sel). Menyalin
 * seluruhnya dari dokumen yang dibagikan lewat obrolan (bukan berkas
 * yang dapat dibaca ulang secara terprogram) membawa risiko salah
 * ketik pada angka gaji sungguhan — tidak dapat diterima untuk data
 * sekritis ini. Baris 1 cukup untuk seluruh pegawai contoh Wave 1
 * (emp_employees.salary_step default 1, belum ada riwayat kenaikan
 * berkala). Baris 2–35 menunggu berkas BPP asli agar dapat dibaca
 * presisi (lihat Domain/SalaryScaleRepository).
 *
 * ⚠️ Verifikasi ulang 19 angka di bawah terhadap Lampiran II asli
 * sebelum dipakai untuk perhitungan sungguhan.
 */
return new class extends Migration
{
    private const SK_GAJI = 'BPP/137/03/64/2026';

    private const EFFECTIVE_FROM = '2026-01-13';

    /** Person Grade 1..19 => Imbalan Kerja baris 1, dalam Rupiah. */
    private const ROW_ONE = [
        1 => 1_042_000,
        2 => 1_250_000,
        3 => 1_500_000,
        4 => 1_800_000,
        5 => 1_691_000,
        6 => 1_860_000,
        7 => 1_945_000,
        8 => 2_050_000,
        9 => 2_153_000,
        10 => 2_236_000,
        11 => 2_572_000,
        12 => 2_957_000,
        13 => 3_401_000,
        14 => 3_911_000,
        15 => 4_693_000,
        16 => 5_632_000,
        17 => 7_040_000,
        18 => 8_800_000,
        19 => 9_500_000,
    ];

    public function up(): void
    {
        foreach (self::ROW_ONE as $personGrade => $rupiah) {
            DB::table('pay_salary_scale')->insert([
                'id' => (string) Str::uuid(),
                'person_grade' => $personGrade,
                'step' => 1,
                'amount_cents' => $rupiah * 100,
                'effective_from' => self::EFFECTIVE_FROM,
                'effective_to' => null,
                'source_document' => self::SK_GAJI,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pay_salary_scale')->where('step', 1)->where('source_document', self::SK_GAJI)->delete();
    }
};
