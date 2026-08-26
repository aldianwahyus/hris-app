<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

use App\Modules\Payroll\Domain\DeductionType;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Template CSV impor potongan — berisi seluruh pegawai pada run +
 * penghasilan sebelum potongan (angka TERBAIK yang tersedia sekarang,
 * take_home_partial_cents — Gaji Kotor asli masih terblokir Lampiran
 * III, lihat PayslipComponents) + baris di-PREFILL dari potongan yang
 * SUDAH ada supaya unduh-ulang tidak menghapus histori kerja admin
 * cabang. Bila satu pegawai punya beberapa baris potongan jenis yang
 * sama (dari input manual berulang), nilainya DIJUMLAHKAN untuk satu
 * kolom template — mengunggah ulang akan menggantikannya jadi satu
 * baris saja (semantik REPLACE, lihat ImportPayslipDeductions).
 */
final class GeneratePayslipDeductionTemplate
{
    public function handle(string $runId): string
    {
        $rows = DB::table('pay_payslips as s')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->where('s.payroll_run_id', $runId)
            ->orderBy('e.full_name')
            ->select('s.id as payslip_id', 'e.nrp', 'e.full_name', 's.take_home_partial_cents')
            ->get();

        $deductionsByPayslip = DB::table('pay_payslip_deductions')
            ->whereIn('payslip_id', $rows->pluck('payslip_id'))
            ->get()
            ->groupBy('payslip_id');

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuat berkas template sementara.');
        }

        // BOM UTF-8 + baris "sep=," WAJIB — tanpa ini Excel membaca
        // delimiter memakai pengaturan region Windows (di Indonesia
        // biasanya titik koma), membuat SELURUH baris jatuh ke satu
        // kolom alih-alih terpisah per kolom. "sep=," memaksa Excel
        // selalu memakai koma, apa pun region-nya (lihat
        // ImportPayslipDeductions::skipExcelSepDirective() sisi baca).
        fwrite($handle, "\xEF\xBB\xBF"."sep=,\r\n");

        $header = ['nrp', 'nama', 'penghasilan_sebelum_potongan'];

        foreach (DeductionType::cases() as $type) {
            $header[] = 'potongan_'.$type->value;
            $header[] = 'catatan_'.$type->value;
        }

        fputcsv($handle, $header);

        foreach ($rows as $row) {
            $existingByType = $deductionsByPayslip->get($row->payslip_id, collect())->groupBy('deduction_type');

            $line = [
                $row->nrp,
                $row->full_name,
                (string) (int) round($row->take_home_partial_cents / 100),
            ];

            foreach (DeductionType::cases() as $type) {
                $matching = $existingByType->get($type->value, collect());
                $sumCents = (int) $matching->sum('amount_cents');

                $line[] = $sumCents > 0 ? (string) (int) round($sumCents / 100) : '';
                $line[] = (string) $matching->pluck('note')->filter()->implode('; ');
            }

            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
