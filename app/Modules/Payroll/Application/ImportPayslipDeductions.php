<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Payroll\Domain\DeductionType;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor massal potongan lewat template CSV (lihat
 * GeneratePayslipDeductionTemplate untuk kolomnya) — hanya selama
 * payroll run berstatus draft.
 *
 * Semantik REPLACE per pegawai: baris di berkas menggantikan SELURUH
 * potongan pegawai itu pada run ini (bukan menambah) — kolom kosong/nol
 * berarti "tidak ada potongan jenis itu", termasuk MENGHAPUS potongan
 * jenis itu bila sebelumnya ada. Ini supaya admin cabang bisa unduh
 * ulang, koreksi, unggah ulang tanpa duplikasi.
 *
 * Baris gagal (NRP tak dikenal/di luar run ini, nilai bukan angka)
 * DILEWATI dan dihitung — TIDAK menggagalkan seluruh impor, pola sama
 * persis ImportAttendanceDeviceScans.
 */
final class ImportPayslipDeductions
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $runId, string $filePath, AuditActor $actor): PayslipDeductionImportResult
    {
        $run = DB::table('pay_payroll_runs')->where('id', $runId)->first();

        if ($run === null) {
            throw new RuntimeException("Payroll run (id: {$runId}) tidak ditemukan.");
        }

        if ($run->status !== 'draft') {
            throw new RuntimeException('Payroll run sudah tidak berstatus draft — potongan tidak dapat diimpor.');
        }

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Berkas tidak dapat dibaca: {$filePath}");
        }

        $imported = 0;
        $skipped = 0;
        $skippedReasons = [];
        $now = new DateTimeImmutable;

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

                $payslipId = DB::table('pay_payslips as s')
                    ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
                    ->where('s.payroll_run_id', $runId)
                    ->where('e.nrp', $nrp)
                    ->value('s.id');

                if ($payslipId === null) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: NRP \"{$nrp}\" tidak ditemukan pada run ini.";

                    continue;
                }

                $entries = [];
                $rowFailed = false;

                foreach (DeductionType::cases() as $type) {
                    $rawAmount = trim((string) ($row[$columns['potongan_'.$type->value]] ?? ''));

                    if ($rawAmount === '' || $rawAmount === '0') {
                        continue;
                    }

                    if (preg_match('/^\d+$/', $rawAmount) !== 1) {
                        $skipped++;
                        $skippedReasons[] = "Baris {$lineNumber}: jumlah potongan \"{$type->label()}\" bukan angka (\"{$rawAmount}\").";
                        $rowFailed = true;

                        break;
                    }

                    $note = trim((string) ($row[$columns['catatan_'.$type->value]] ?? ''));

                    $entries[] = [
                        'id' => (string) Uuid7::generate(),
                        'payslip_id' => $payslipId,
                        'deduction_type' => $type->value,
                        'amount_cents' => ((int) $rawAmount) * 100,
                        'note' => $note !== '' ? $note : null,
                        'created_by' => $actor->actorId,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'version' => 1,
                    ];
                }

                if ($rowFailed) {
                    continue;
                }

                DB::table('pay_payslip_deductions')->where('payslip_id', $payslipId)->delete();

                if ($entries !== []) {
                    DB::table('pay_payslip_deductions')->insert($entries);
                }

                $imported++;
            }
        } finally {
            fclose($handle);
        }

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $actor,
            auditableType: 'payroll_run',
            auditableId: $runId,
            action: AuditAction::Updated,
            newValues: ['potongan_diimpor' => $imported, 'baris_dilewati' => $skipped],
        ));

        return new PayslipDeductionImportResult(imported: $imported, skipped: $skipped, skippedReasons: $skippedReasons);
    }

    /**
     * Template terunduh (GeneratePayslipDeductionTemplate) diawali baris
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

        $required = ['nrp'];

        foreach (DeductionType::cases() as $type) {
            $required[] = 'potongan_'.$type->value;
            $required[] = 'catatan_'.$type->value;
        }

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
