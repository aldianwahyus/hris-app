<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nilai konfigurasi awal — seluruhnya berasal dari dokumen kebijakan resmi
 * bertanda tangan Direksi. Setiap nilai mencantumkan nomor SK sebagai dasar,
 * sehingga auditor dapat menelusuri asal-usul setiap angka.
 *
 * CATATAN: PAYROLL_CUTOFF_DAY sengaja TIDAK di-seed. Nilainya (tanggal 25)
 * masih berasal dari jawaban lisan, belum ada dokumen tertulis. Dua kali
 * jawaban lisan terbukti meleset dari dokumen (jam mulai lembur & tarif),
 * sehingga nilai ini harus diverifikasi lebih dulu — lihat risiko RA-8.
 */
return new class extends Migration
{
    private const SK_LEMBUR = 'KEP/1827/06/64/2026';
    private const SK_CUTI = 'BPP/1087/03/64/2026';
    private const SK_GAJI = 'BPP/137/03/64/2026';

    public function up(): void
    {
        $parameters = [
            // Lembur — SK KEP/1827/06/64/2026, efektif 1 Juli 2026
            ['OVT_RATE_MGR_SPV_OFC', 'Tarif lembur biasa — Manager/Supervisor/Officer', 'money', 'rupiah', 'Overtime', '30000', '2026-07-01', self::SK_LEMBUR],
            ['OVT_RATE_ASST_ADM', 'Tarif lembur biasa — Asisten Administrasi', 'money', 'rupiah', 'Overtime', '25000', '2026-07-01', self::SK_LEMBUR],
            ['OVT_RATE_NON_ADMIN', 'Tarif lembur biasa — Non Admin', 'money', 'rupiah', 'Overtime', '20000', '2026-07-01', self::SK_LEMBUR],
            ['OVT_CRASH_PER_DAY', 'Tarif Crash Program per hari (lumpsum)', 'money', 'rupiah', 'Overtime', '250000', '2026-07-01', self::SK_LEMBUR],
            ['OVT_MEAL_MAX', 'Batas konsumsi lembur', 'money', 'rupiah', 'Overtime', '60000', '2026-07-01', self::SK_LEMBUR],
            ['OVT_WEEKLY_CAP_HOURS', 'Plafon lembur biasa per minggu', 'integer', 'jam', 'Overtime', '18', '2026-07-01', self::SK_LEMBUR],
            ['OVT_DAILY_MAX_HOURS', 'Maksimal lembur biasa per hari', 'integer', 'jam', 'Overtime', '4', '2026-07-01', self::SK_LEMBUR],
            ['OVT_RETRO_WINDOW_DAYS', 'Jendela pengajuan retroaktif hingga persetujuan', 'integer', 'hari', 'Overtime', '30', '2026-07-01', self::SK_LEMBUR],

            // Cuti — BPP/1087/03/64/2026, efektif 10 Maret 2026
            ['LEAVE_ANNUAL_ABOVE_MGR', 'Kuota cuti tahunan di atas Manager', 'integer', 'hari', 'Leave', '15', '2026-03-10', self::SK_CUTI],
            ['LEAVE_ANNUAL_UPTO_MGR', 'Kuota cuti tahunan s.d. Manager & kontrak', 'integer', 'hari', 'Leave', '12', '2026-03-10', self::SK_CUTI],
            ['LEAVE_UMROH_MAX_DAYS', 'Kuota cuti tahunan untuk umroh', 'integer', 'hari', 'Leave', '14', '2026-03-10', self::SK_CUTI],
            ['LEAVE_CARRY_FORWARD_MAX', 'Maksimal sisa cuti dibawa ke tahun berikutnya', 'integer', 'hari', 'Leave', '2', '2026-03-10', self::SK_CUTI],
            ['LEAVE_BLOCK_LEAVE_MIN', 'Minimal block leave pengambilan pertama', 'integer', 'hari', 'Leave', '5', '2026-03-10', self::SK_CUTI],
            ['LEAVE_LONG_SERVICE_YEARS', 'Masa kerja untuk hak cuti besar', 'integer', 'tahun', 'Leave', '6', '2026-03-10', self::SK_CUTI],
            ['LEAVE_ALLOWANCE_TRANSFER_DAY', 'Tanggal pelimpahan bekal cuti akhir tahun', 'string', null, 'Leave', '12-30', '2026-03-10', self::SK_CUTI],

            // Iuran — BPP/137/03/64/2026, efektif 13 Januari 2026
            ['CONTRIB_PENSION_EMPLOYEE_PCT', 'Iuran pensiun beban pegawai (dasar: imbalan kerja)', 'decimal', 'persen', 'Payroll', '7.00', '2026-01-13', self::SK_GAJI],
            ['CONTRIB_THT_TOTAL_PCT', 'Iuran THT total (dasar: imbalan kerja)', 'decimal', 'persen', 'Payroll', '8.05', '2026-01-13', self::SK_GAJI],
            ['CONTRIB_THT_EMPLOYEE_PCT', 'Iuran THT beban pegawai', 'decimal', 'persen', 'Payroll', '5.00', '2026-01-13', self::SK_GAJI],
            ['CONTRIB_ASTEK_TOTAL_PCT', 'Iuran Astek/BPJS TK total (dasar: GAJI KOTOR)', 'decimal', 'persen', 'Payroll', '9.24', '2026-01-13', self::SK_GAJI],
            ['CONTRIB_ASTEK_EMPLOYEE_PCT', 'Iuran Astek beban pegawai', 'decimal', 'persen', 'Payroll', '3.00', '2026-01-13', self::SK_GAJI],
            ['ALLOWANCE_KEMAHALAN_PCT', 'Tunjangan kemahalan (dari tunj. jabatan + kinerja)', 'decimal', 'persen', 'Payroll', '20.00', '2026-01-13', self::SK_GAJI],

            // Pensiun
            ['RETIREMENT_NORMAL_AGE', 'Usia pensiun normal', 'integer', 'tahun', 'Benefit', '56', '2025-07-02', 'BPP/2385/06/64/2025'],
            ['RETIREMENT_YOUNG_AGE', 'Usia pembayaran pensiun muda', 'integer', 'tahun', 'Benefit', '46', '2025-07-02', 'BPP/2385/06/64/2025'],
            ['AWARD_RETIREMENT_MULTIPLIER', 'Pengali Penghargaan Purna Bakti', 'integer', 'kali', 'Benefit', '8', '2025-07-02', 'BPP/2385/06/64/2025'],
        ];

        foreach ($parameters as [$code, $name, $type, $unit, $module, $value, $from, $doc]) {
            $parameterId = (string) Str::uuid();

            DB::table('cfg_parameters')->insert([
                'id' => $parameterId,
                'code' => $code,
                'name' => $name,
                'value_type' => $type,
                'unit' => $unit,
                'owner_module' => $module,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);

            DB::table('cfg_parameter_values')->insert([
                'id' => (string) Str::uuid(),
                'parameter_id' => $parameterId,
                'value' => $value,
                'effective_from' => $from,
                'effective_to' => null,
                'source_document' => $doc,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cfg_parameter_values')->delete();
        DB::table('cfg_parameters')->delete();
    }
};
