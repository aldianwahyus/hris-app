<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah tahap pipeline satu lamaran — TIDAK ada di daftar kelas
 * Application pada rencana awal, ditambahkan karena "HC kelola
 * pipeline: ubah status per tahap" butuh SATU titik penegakan
 * transisi yang valid (bukan tersebar di controller), pola sama
 * UpdateTicketStatus pada Helpdesk.
 */
final class UpdateApplicationStage
{
    private const VALID_STAGES = ['melamar', 'seleksi_berkas', 'wawancara', 'penawaran', 'diterima', 'ditolak'];

    public function handle(string $applicationId, string $newStage, ?string $stageNotes): void
    {
        if (! in_array($newStage, self::VALID_STAGES, true)) {
            throw new DomainException("Tahap \"{$newStage}\" tidak dikenal.");
        }

        $application = DB::table('rec_applications')->where('id', $applicationId)->first();

        if ($application === null) {
            throw new DomainException('Lamaran tidak ditemukan.');
        }

        if (in_array($application->status, ['diterima', 'ditolak'], true)) {
            throw new DomainException('Lamaran yang sudah berstatus final tidak dapat diubah tahapnya lagi.');
        }

        DB::table('rec_applications')->where('id', $applicationId)->update([
            'status' => $newStage,
            'stage_notes' => $stageNotes,
            'updated_at' => new DateTimeImmutable,
            'version' => $application->version + 1,
        ]);
    }
}
