<?php

declare(strict_types=1);

namespace App\Modules\Offboarding\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi wawancara keluar untuk satu pengajuan pemisahan — boleh
 * diisi pegawai yang bersangkutan (ESS) ATAU HC, SATU kali per
 * pemisahan (UNIQUE separation_id, ditegakkan di sini DAN di skema).
 */
final class SubmitExitInterview
{
    public function handle(
        string $separationId,
        string $employeeId,
        ?string $reasonDetail,
        ?int $satisfactionRating,
        ?bool $wouldRecommend,
        ?string $comments,
    ): string {
        $separation = DB::table('off_separation_requests')->where('id', $separationId)->first();

        if ($separation === null) {
            throw new DomainException('Pengajuan pemisahan tidak ditemukan.');
        }

        if (! in_array($separation->status, ['approved', 'selesai'], true)) {
            throw new DomainException('Wawancara keluar hanya dapat diisi setelah pemisahan disetujui.');
        }

        if (DB::table('off_exit_interviews')->where('separation_id', $separationId)->exists()) {
            throw new DomainException('Wawancara keluar untuk pemisahan ini sudah pernah diisi.');
        }

        $id = (string) Uuid7::generate();

        DB::table('off_exit_interviews')->insert([
            'id' => $id,
            'separation_id' => $separationId,
            'employee_id' => $employeeId,
            'reason_detail' => $reasonDetail,
            'satisfaction_rating' => $satisfactionRating,
            'would_recommend' => $wouldRecommend,
            'comments' => $comments,
            'submitted_at' => new DateTimeImmutable,
        ]);

        return $id;
    }
}
