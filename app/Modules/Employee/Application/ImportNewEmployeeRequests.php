<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Impor massal usulan pegawai baru dari CSV — SATU baris CSV = SATU
 * baris `emp_new_employee_requests` pending lewat SubmitNewEmployeeRequest
 * yang SUDAH ADA (dipakai APA ADANYA, tidak diubah) — TIDAK PERNAH
 * menulis emp_employees langsung, maker-checker (§6.3) tetap tegak:
 * hr_approver WAJIB menyetujui satu per satu lewat DecideNewEmployeeRequest
 * yang sudah ada, persis seperti usulan satuan lewat
 * SystemAdminEmployeeController::store().
 *
 * Pola parsing PERSIS ImportAttendanceDeviceScans: fgetcsv murni (tidak
 * ada library Excel), header case-insensitive fleksibel, baris rusak
 * DILEWATI+dicatat alasannya (bukan gagal total).
 *
 * Kode kantor/jabatan pada CSV diresolusi ke office_id/position_id lewat
 * `md_offices`/`md_positions` — kode yang tidak dikenal ATAU kantor/
 * jabatan yang sudah dinonaktifkan (is_active=false) DILEWATI, sama
 * seperti kode tidak dikenal (tidak masuk akal menaruh pegawai baru ke
 * kantor/jabatan yang sedang dinonaktifkan).
 */
final class ImportNewEmployeeRequests
{
    public function __construct(
        private readonly SubmitNewEmployeeRequest $submitNew,
    ) {}

    public function handle(string $filePath, string $requestedBy, AuditActor $actor): ImportResult
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

            // BOM UTF-8 bisa menempel di kolom PERTAMA kalau berkas tidak
            // diawali baris "sep=," (mis. header langsung diikuti BOM dari
            // alat lain) — dilepas di sini supaya "nrp" tetap cocok, bukan
            // "\xEF\xBB\xBFnrp" yang tidak akan pernah ditemukan mapHeader().
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

                // isset($columns[$key]) dulu — kolom OPSIONAL (tanggal_lahir,
                // jenis_kelamin, email, golongan, job_grade) boleh tidak ada
                // sama sekali di header, akses $columns[$key] langsung tanpa
                // ini akan memicu "Undefined array key" di tiap baris.
                $value = fn (string $key): string => isset($columns[$key])
                    ? trim((string) ($row[$columns[$key]] ?? ''))
                    : '';

                $kodeKantor = $value('kode_kantor');
                $kodeJabatan = $value('kode_jabatan');

                $officeId = $kodeKantor === '' ? null : DB::table('md_offices')
                    ->where('code', $kodeKantor)->where('is_active', true)->value('id');

                if ($officeId === null) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: kode kantor \"{$kodeKantor}\" tidak ditemukan atau nonaktif.";

                    continue;
                }

                $positionId = $kodeJabatan === '' ? null : DB::table('md_positions')
                    ->where('code', $kodeJabatan)->where('is_active', true)->value('id');

                if ($positionId === null) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: kode jabatan \"{$kodeJabatan}\" tidak ditemukan atau nonaktif.";

                    continue;
                }

                // Atasan Langsung diresolusi dari NRP (opsional, murni untuk
                // Struktur Organisasi — TIDAK memengaruhi wewenang) — NRP
                // yang tidak dikenal diabaikan diam-diam (null), TIDAK
                // menggagalkan baris, beda dari kode_kantor/kode_jabatan
                // yang wajib valid.
                $nrpAtasan = $value('nrp_atasan');
                $supervisorId = $nrpAtasan === '' ? null : DB::table('emp_employees')->where('nrp', $nrpAtasan)->value('id');

                $rowData = [
                    'nrp' => $value('nrp'),
                    'full_name' => $value('nama'),
                    'birth_date' => $value('tanggal_lahir') ?: null,
                    'gender' => $value('jenis_kelamin') ?: null,
                    'email' => $value('email') ?: null,
                    'join_date' => $value('tanggal_masuk'),
                    'employment_status' => $value('status_kepegawaian'),
                    'office_id' => $officeId,
                    'position_id' => $positionId,
                    'person_grade' => $value('golongan') !== '' ? (int) $value('golongan') : null,
                    'job_grade' => $value('job_grade') !== '' ? (int) $value('job_grade') : null,
                    'marital_status' => $value('status_kawin') ?: null,
                    'tanggungan' => $value('tanggungan') !== '' ? (int) $value('tanggungan') : null,
                    'permanent_date' => $value('tanggal_tetap') ?: null,
                    'supervisor_id' => $supervisorId,
                    'division' => $value('divisi') ?: null,
                    'agama' => $value('agama') ?: null,
                    'nomor_ktp' => $value('nomor_ktp') ?: null,
                    'nomor_npwp' => $value('nomor_npwp') ?: null,
                    'bpjs_tenaga_kerja' => $value('bpjs_tenaga_kerja') ?: null,
                    'bpjs_kesehatan' => $value('bpjs_kesehatan') ?: null,
                    'nomor_simpeda' => $value('nomor_simpeda') ?: null,
                    'nomor_tambora_rencana' => $value('nomor_tambora_rencana') ?: null,
                    'tmt_pangkat' => $value('tmt_pangkat') ?: null,
                    'alamat' => $value('alamat') ?: null,
                    'no_telepon' => $value('no_telepon') ?: null,
                    'kontak_darurat_nama' => $value('kontak_darurat_nama') ?: null,
                    'kontak_darurat_hubungan' => $value('kontak_darurat_hubungan') ?: null,
                    'kontak_darurat_telepon' => $value('kontak_darurat_telepon') ?: null,
                    'pendidikan_terakhir' => $value('pendidikan_terakhir') ?: null,
                    'pendidikan_jurusan' => $value('pendidikan_jurusan') ?: null,
                ];

                // Kaidah SAMA PERSIS SystemAdminEmployeeController::store()
                // — disalin, bukan diimpor lintas layer HTTP↔Application
                // (Application tidak boleh bergantung ke Illuminate\Http\Request).
                $validator = Validator::make($rowData, [
                    'nrp' => ['required', 'string', 'max:30'],
                    'full_name' => ['required', 'string', 'max:150'],
                    'birth_date' => ['nullable', 'date'],
                    'gender' => ['nullable', 'string', 'in:L,P'],
                    'email' => ['nullable', 'email', 'max:150'],
                    'join_date' => ['required', 'date'],
                    'employment_status' => ['required', 'string', 'in:tetap,trainee,kontrak,outsource'],
                    'office_id' => ['required', 'uuid'],
                    'position_id' => ['required', 'uuid'],
                    'person_grade' => ['nullable', 'integer', 'min:1', 'max:255'],
                    'job_grade' => ['nullable', 'integer', 'min:1', 'max:255'],
                    'marital_status' => ['nullable', 'string', 'in:belum menikah,menikah'],
                    'tanggungan' => ['nullable', 'integer', 'min:0', 'max:3'],
                    'permanent_date' => ['nullable', 'date'],
                    'supervisor_id' => ['nullable', 'uuid'],
                    'division' => ['nullable', 'string', 'max:100'],
                    'agama' => ['nullable', 'string', 'in:Islam,Kristen Protestan,Kristen Katolik,Hindu,Buddha,Konghucu'],
                    'nomor_ktp' => ['nullable', 'string', 'max:20'],
                    'nomor_npwp' => ['nullable', 'string', 'max:25'],
                    'bpjs_tenaga_kerja' => ['nullable', 'string', 'max:30'],
                    'bpjs_kesehatan' => ['nullable', 'string', 'max:30'],
                    'nomor_simpeda' => ['nullable', 'string', 'max:30'],
                    'nomor_tambora_rencana' => ['nullable', 'string', 'max:30'],
                    'tmt_pangkat' => ['nullable', 'date'],
                    'alamat' => ['nullable', 'string', 'max:2000'],
                    'no_telepon' => ['nullable', 'string', 'max:20'],
                    'kontak_darurat_nama' => ['nullable', 'string', 'max:150'],
                    'kontak_darurat_hubungan' => ['nullable', 'string', 'max:50'],
                    'kontak_darurat_telepon' => ['nullable', 'string', 'max:20'],
                    'pendidikan_terakhir' => ['nullable', 'string', 'max:30'],
                    'pendidikan_jurusan' => ['nullable', 'string', 'max:100'],
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: ".$validator->errors()->first();

                    continue;
                }

                // Aturan SAMA ProfileChangeValidator/SystemAdminEmployeeController::store():
                // status "tetap" wajib punya tanggal jadi pegawai tetap.
                if ($validator->validated()['employment_status'] === 'tetap' && ($validator->validated()['permanent_date'] ?? null) === null) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: status \"tetap\" wajib mengisi tanggal_tetap.";

                    continue;
                }

                try {
                    $this->submitNew->handle($validator->validated(), $requestedBy, $actor);
                    $imported++;
                } catch (InvalidArgumentException $e) {
                    $skipped++;
                    $skippedReasons[] = "Baris {$lineNumber}: {$e->getMessage()}";
                }
            }
        } finally {
            fclose($handle);
        }

        return new ImportResult(imported: $imported, skipped: $skipped, skippedReasons: $skippedReasons);
    }

    /**
     * Template terunduh (EmployeeImportController::template()) diawali
     * baris "sep=," — petunjuk resmi Excel supaya delimiter koma dipakai
     * apa pun pengaturan region Windows pengguna (tanpa ini Excel di
     * region Indonesia biasa membaca titik koma, membuat SELURUH baris
     * jatuh ke satu kolom). Excel SENDIRI tidak menyimpan ulang baris ini
     * saat file disimpan sebagai CSV, tapi kalau pengguna mengunggah
     * berkas mentah tanpa lewat Excel dulu, baris itu masih ada — dilewati
     * di sini SEBELUM baris header sungguhan dibaca, apa pun asalnya.
     */
    /** @param  resource  $handle */
    private function skipExcelSepDirective(mixed $handle): void
    {
        $position = ftell($handle);
        $firstLine = fgets($handle);

        if ($firstLine !== false) {
            $withoutBom = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;

            if (preg_match('/^sep=.\s*$/i', trim($withoutBom)) === 1) {
                return; // baris "sep=," dikonsumsi, fgetcsv berikutnya membaca header sungguhan
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

        $required = ['nrp', 'nama', 'tanggal_masuk', 'status_kepegawaian', 'kode_kantor', 'kode_jabatan'];
        $optional = [
            'tanggal_lahir', 'jenis_kelamin', 'email', 'golongan', 'job_grade',
            'status_kawin', 'tanggungan', 'tanggal_tetap', 'nrp_atasan', 'divisi',
            'agama', 'nomor_ktp', 'nomor_npwp', 'bpjs_tenaga_kerja', 'bpjs_kesehatan',
            'nomor_simpeda', 'nomor_tambora_rencana', 'tmt_pangkat',
            'alamat', 'no_telepon', 'kontak_darurat_nama', 'kontak_darurat_hubungan',
            'kontak_darurat_telepon', 'pendidikan_terakhir', 'pendidikan_jurusan',
        ];

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
