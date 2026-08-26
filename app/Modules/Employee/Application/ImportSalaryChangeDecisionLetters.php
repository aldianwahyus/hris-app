<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Modules\Employee\Domain\SkType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor massal SK Perubahan Gaji lewat template CSV (lihat
 * GenerateSalaryChangeTemplate untuk kolomnya) — satu SK (nomor/
 * tanggal/keterangan/berkas SAMA) untuk seluruh baris yang punya
 * perubahan, tapi NILAI gaji per baris BERBEDA-BEDA sesuai isian admin.
 *
 * Baris tanpa satu pun kolom "_baru" terisi dihitung skippedNoChange
 * (BUKAN error — pegawai itu memang tidak berubah, implementasi
 * literal "boleh dikosongkan"). Baris dengan NRP tak dikenal/di luar
 * lingkup aktor/nilai bukan angka dihitung skipped (error) dengan
 * alasan — pola sama ImportPayslipDeductions: baris gagal DILEWATI,
 * TIDAK menggagalkan seluruh impor.
 */
final class ImportSalaryChangeDecisionLetters
{
    public function __construct(private readonly RecordDecisionLetter $record) {}

    /** @param  array<int, string>  $actorRoles */
    public function handle(
        string $filePath,
        string $skNumber,
        DateTimeImmutable $skDate,
        ?DateTimeImmutable $effectiveDate,
        string $description,
        ?string $documentPath,
        ?string $documentOriginalName,
        array $actorRoles,
        ?string $actorOfficeId,
        string $requestedBy,
        AuditActor $actor,
    ): SalaryChangeImportResult {
        $bankWide = array_intersect(['hr_approver', 'system_admin'], $actorRoles) !== [];

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Berkas tidak dapat dibaca: {$filePath}");
        }

        $imported = 0;
        $skippedNoChange = 0;
        $skipped = 0;
        $skippedReasons = [];

        try {
            $this->skipExcelSepDirective($handle);

            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('Berkas kosong atau bukan CSV yang valid.');
            }

            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            }

            $columns = $this->mapHeader($header);
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($row === [null]) {
                    continue; // baris kosong di akhir berkas
                }

                $nrp = trim((string) ($row[$columns['nrp']] ?? ''));

                if ($nrp === '') {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: NRP kosong.";

                    continue;
                }

                $employee = DB::table('emp_employees')->where('nrp', $nrp)->first(['id', 'office_id']);

                if ($employee === null) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: NRP \"{$nrp}\" tidak ditemukan.";

                    continue;
                }

                if (! $bankWide && $employee->office_id !== $actorOfficeId) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: NRP \"{$nrp}\" di luar lingkup Anda.";

                    continue;
                }

                $rawGolongan = trim((string) ($row[$columns['golongan_baru']] ?? ''));
                $rawStep = trim((string) ($row[$columns['step_baru']] ?? ''));
                $rawTunjanganJabatan = trim((string) ($row[$columns['tunjangan_jabatan_baru']] ?? ''));
                $rawTunjanganPenyesuaian = trim((string) ($row[$columns['tunjangan_penyesuaian_baru']] ?? ''));

                if ($rawGolongan === '' && $rawStep === '' && $rawTunjanganJabatan === '' && $rawTunjanganPenyesuaian === '') {
                    $skippedNoChange++;

                    continue;
                }

                $numericFields = [
                    'golongan_baru' => $rawGolongan,
                    'step_baru' => $rawStep,
                    'tunjangan_jabatan_baru' => $rawTunjanganJabatan,
                    'tunjangan_penyesuaian_baru' => $rawTunjanganPenyesuaian,
                ];
                $rowFailed = false;

                foreach ($numericFields as $label => $value) {
                    if ($value !== '' && preg_match('/^\d+$/', $value) !== 1) {
                        $skipped++;
                        $skippedReasons[] = "Baris {$lineNumber}: kolom \"{$label}\" bukan angka (\"{$value}\").";
                        $rowFailed = true;

                        break;
                    }
                }

                if ($rowFailed) {
                    continue;
                }

                $proposedChanges = array_filter([
                    'person_grade' => $rawGolongan !== '' ? (int) $rawGolongan : null,
                    'salary_step' => $rawStep !== '' ? (int) $rawStep : null,
                    'tunjangan_jabatan_cents' => $rawTunjanganJabatan !== '' ? ((int) $rawTunjanganJabatan) * 100 : null,
                    'tunjangan_penyesuaian_cents' => $rawTunjanganPenyesuaian !== '' ? ((int) $rawTunjanganPenyesuaian) * 100 : null,
                ], fn ($v) => $v !== null);

                try {
                    $this->record->handle(
                        employeeId: $employee->id,
                        skType: SkType::PerubahanGaji,
                        skNumber: $skNumber,
                        skDate: $skDate,
                        effectiveDate: $effectiveDate,
                        description: $description,
                        documentPath: $documentPath,
                        documentOriginalName: $documentOriginalName,
                        proposedChanges: $proposedChanges,
                        requestedBy: $requestedBy,
                        actor: $actor,
                    );

                    $imported++;
                } catch (DomainException|RuntimeException $e) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: {$e->getMessage()}";
                }
            }
        } finally {
            fclose($handle);
        }

        return new SalaryChangeImportResult(
            imported: $imported,
            skippedNoChange: $skippedNoChange,
            skipped: $skipped,
            skippedReasons: $skippedReasons,
        );
    }

    /**
     * Template terunduh (GenerateSalaryChangeTemplate) diawali baris
     * "sep=," — petunjuk resmi Excel supaya delimiter koma dipakai apa
     * pun pengaturan region Windows pengguna. Excel sendiri tidak
     * menyimpan ulang baris ini saat file disimpan sebagai CSV, tapi
     * kalau pengguna mengunggah berkas mentah tanpa lewat Excel dulu,
     * baris itu masih ada — dilewati di sini sebelum header sungguhan
     * dibaca, apa pun asalnya.
     *
     * @param  resource  $handle
     */
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
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $required = [
            'nrp', 'golongan_baru', 'step_baru', 'tunjangan_jabatan_baru', 'tunjangan_penyesuaian_baru',
        ];

        $columns = [];

        foreach ($required as $name) {
            $index = array_search($name, $normalized, true);

            if ($index === false) {
                throw new RuntimeException(
                    "Header berkas harus memuat kolom \"{$name}\" — gunakan template yang diunduh dari aplikasi. "
                    .'Kolom ditemukan: '.implode(', ', $normalized)
                );
            }

            $columns[$name] = $index;
        }

        return $columns;
    }
}
