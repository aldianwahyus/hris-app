<?php

declare(strict_types=1);

namespace App\Modules\Office\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Impor massal kantor dari CSV — TIDAK seperti impor pegawai
 * (maker-checker, tetap menunggu persetujuan), md_offices TIDAK punya
 * antrean persetujuan sama sekali (lihat OfficeController) — SATU
 * baris CSV LANGSUNG jadi SATU baris `md_offices` aktif, aturan valid
 * SAMA PERSIS OfficeController::store() (disalin, bukan diimpor lintas
 * layer HTTP↔Application).
 *
 * Baris diproses SATU PER SATU (bukan batch di akhir) SENGAJA — supaya
 * kantor INDUK yang muncul lebih dulu di berkas yang SAMA sudah
 * tersimpan dan bisa langsung dirujuk kantor ANAK di baris-baris
 * berikutnya lewat kode_kantor_induk, tanpa perlu dua kali impor
 * terpisah (induk dulu, baru anak).
 *
 * Pola parsing (fgetcsv murni, header fleksibel, baris rusak DILEWATI
 * + dicatat alasannya) PERSIS ImportAttendanceDeviceScans/
 * ImportNewEmployeeRequests.
 */
final class ImportOffices
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $filePath, AuditActor $actor): ImportResult
    {
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

                $value = fn (string $key): string => isset($columns[$key])
                    ? trim((string) ($row[$columns[$key]] ?? ''))
                    : '';

                $rowData = [
                    'code' => $value('kode'),
                    'name' => $value('nama'),
                    'address' => $value('alamat') ?: null,
                    'office_type' => $value('tipe'),
                    'office_class' => $value('kelas') ?: null,
                    'timezone' => $value('zona_waktu'),
                ];

                $validator = Validator::make($rowData, [
                    'code' => ['required', 'string', 'max:20'],
                    'name' => ['required', 'string', 'max:150'],
                    'address' => ['nullable', 'string', 'max:500'],
                    'office_type' => ['required', 'string', 'in:head_office,branch,sub_branch,functional'],
                    'office_class' => ['nullable', 'string', 'max:30'],
                    'timezone' => ['required', 'string', 'max:40'],
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: ".$validator->errors()->first();

                    continue;
                }

                $validated = $validator->validated();

                $codeTaken = DB::table('md_offices')->where('code', $validated['code'])->exists();

                if ($codeTaken) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: kode kantor \"{$validated['code']}\" sudah dipakai.";

                    continue;
                }

                $kodeIndukValue = $value('kode_kantor_induk');
                $parentOfficeId = null;

                if ($kodeIndukValue !== '') {
                    $parentOfficeId = DB::table('md_offices')
                        ->where('code', $kodeIndukValue)->where('is_active', true)->value('id');

                    if ($parentOfficeId === null) {
                        $skipped++;
                        $skippedReasons[] = "Baris {$lineNumber}: kode kantor induk \"{$kodeIndukValue}\" tidak ditemukan atau nonaktif.";

                        continue;
                    }
                }

                $id = (string) Uuid7::generate();
                $now = new DateTimeImmutable;

                DB::table('md_offices')->insert([
                    'id' => $id,
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'address' => $validated['address'],
                    'office_type' => $validated['office_type'],
                    'office_class' => $validated['office_class'],
                    'parent_office_id' => $parentOfficeId,
                    'timezone' => $validated['timezone'],
                    'geofence_radius_m' => 100,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);

                $this->audit->append(new AuditEntry(
                    occurredAt: $now,
                    actor: $actor,
                    auditableType: 'md_office',
                    auditableId: $id,
                    action: AuditAction::Created,
                    newValues: [...$validated, 'parent_office_id' => $parentOfficeId],
                ));

                $imported++;
            }
        } finally {
            fclose($handle);
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
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $required = ['kode', 'nama', 'tipe', 'zona_waktu'];
        $optional = ['alamat', 'kelas', 'kode_kantor_induk'];

        $columns = [];

        foreach ([...$required, ...$optional] as $key) {
            $index = array_search($key, $normalized, true);

            if ($index !== false) {
                $columns[$key] = $index;
            }
        }

        $missing = array_diff($required, array_keys($columns));

        if ($missing !== []) {
            throw new RuntimeException(
                'Header berkas harus memuat kolom: '.implode(', ', $required).' — '
                .'kolom wajib yang tidak ditemukan: '.implode(', ', $missing).'. '
                .'Kolom ditemukan: '.implode(', ', $normalized)
            );
        }

        return $columns;
    }
}
