<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Kandidat menerima/menolak tawaran lewat tautan token publik —
 * token sekali pakai, hanya berlaku selama status 'menunggu'.
 * Menolak tawaran menutup lamaran itu (status='ditolak') — ATS ini
 * tidak mendukung negosiasi ulang tawaran yang sama, HC bisa
 * mengulang siklus dari lamaran baru bila diperlukan.
 */
final class RespondToJobOffer
{
    public function handle(string $token, bool $accepted): void
    {
        DB::transaction(function () use ($token, $accepted) {
            $offer = DB::table('rec_job_offers')->where('response_token', $token)->lockForUpdate()->first();

            if ($offer === null) {
                throw new DomainException('Tautan tawaran tidak valid.');
            }

            if ($offer->status !== 'menunggu') {
                throw new DomainException('Tawaran ini sudah pernah direspons sebelumnya.');
            }

            $now = new DateTimeImmutable;

            DB::table('rec_job_offers')->where('id', $offer->id)->update([
                'status' => $accepted ? 'diterima' : 'ditolak',
                'responded_at' => $now,
            ]);

            DB::table('rec_applications')->where('id', $offer->application_id)->update([
                'status' => $accepted ? 'diterima' : 'ditolak',
                'updated_at' => $now,
            ]);
        });
    }
}
