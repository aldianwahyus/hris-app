<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Template CSV impor massal SK Perubahan Gaji — berisi seluruh pegawai
 * dalam lingkup aktor (bank-wide untuk hr_approver/system_admin,
 * kantor sendiri untuk hr_admin, pola SAMA ResolveEmployeeForHrAction)
 * + gaji SAAT INI (kolom "_saat_ini", PREFILLED) supaya admin tahu apa
 * yang sedang diubah tanpa perlu lihat layar lain, ditambah kolom
 * "_baru" KOSONG untuk diisi (lihat ImportSalaryChangeDecisionLetters).
 */
final class GenerateSalaryChangeTemplate
{
    /** @param  array<int, string>  $actorRoles */
    public function handle(array $actorRoles, ?string $actorOfficeId): string
    {
        $bankWide = array_intersect(['hr_approver', 'system_admin'], $actorRoles) !== [];

        $query = DB::table('emp_employees as e')
            ->select('e.nrp', 'e.full_name', 'e.person_grade', 'e.salary_step', 'e.tunjangan_jabatan_cents', 'e.tunjangan_penyesuaian_cents');

        if (! $bankWide) {
            $query->where('e.office_id', $actorOfficeId);
        }

        $employees = $query->orderBy('e.full_name')->get();

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuat berkas template sementara.');
        }

        // BOM UTF-8 + baris "sep=," WAJIB — tanpa ini Excel membaca
        // delimiter memakai pengaturan region Windows (di Indonesia
        // biasanya titik koma), membuat SELURUH baris jatuh ke satu
        // kolom alih-alih terpisah per kolom. Lihat
        // ImportSalaryChangeDecisionLetters::skipExcelSepDirective().
        fwrite($handle, "\xEF\xBB\xBF"."sep=,\r\n");

        fputcsv($handle, [
            'nrp', 'nama', 'golongan_saat_ini', 'step_saat_ini',
            'tunjangan_jabatan_saat_ini', 'tunjangan_penyesuaian_saat_ini',
            'golongan_baru', 'step_baru', 'tunjangan_jabatan_baru', 'tunjangan_penyesuaian_baru',
        ]);

        foreach ($employees as $employee) {
            fputcsv($handle, [
                $employee->nrp,
                $employee->full_name,
                $employee->person_grade ?? '',
                $employee->salary_step ?? '',
                (string) (int) round($employee->tunjangan_jabatan_cents / 100),
                (string) (int) round($employee->tunjangan_penyesuaian_cents / 100),
                '', '', '', '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
