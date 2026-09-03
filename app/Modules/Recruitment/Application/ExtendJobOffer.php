<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat tawaran kerja — response_token ACAK (Str::random, sumber
 * random_bytes) dipakai URL publik TANPA login (/tawaran/{token},
 * lihat PublicCareersController::respondToOffer()) supaya kandidat
 * bisa menerima/menolak tanpa akun HCIS.
 */
final class ExtendJobOffer
{
    public function handle(
        string $applicationId,
        string $proposedPositionId,
        string $proposedOfficeId,
        ?string $proposedSalaryNotes,
    ): string {
        $application = DB::table('rec_applications')->where('id', $applicationId)->first();

        if ($application === null) {
            throw new DomainException('Lamaran tidak ditemukan.');
        }

        $hasPendingOffer = DB::table('rec_job_offers')
            ->where('application_id', $applicationId)
            ->where('status', 'menunggu')
            ->exists();

        if ($hasPendingOffer) {
            throw new DomainException('Lamaran ini sudah memiliki tawaran yang masih menunggu respons kandidat.');
        }

        $now = new DateTimeImmutable;
        $id = (string) Uuid7::generate();

        DB::table('rec_job_offers')->insert([
            'id' => $id,
            'application_id' => $applicationId,
            'proposed_position_id' => $proposedPositionId,
            'proposed_office_id' => $proposedOfficeId,
            'proposed_salary_notes' => $proposedSalaryNotes,
            'response_token' => Str::random(48),
            'offered_at' => $now,
            'status' => 'menunggu',
            'responded_at' => null,
        ]);

        DB::table('rec_applications')->where('id', $applicationId)->update([
            'status' => 'penawaran',
            'updated_at' => $now,
            'version' => $application->version + 1,
        ]);

        return $id;
    }
}
