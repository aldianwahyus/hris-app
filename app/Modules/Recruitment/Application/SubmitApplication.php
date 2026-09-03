<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Melamar satu lowongan — dipanggil dari halaman PUBLIK tanpa login
 * (lihat PublicCareersController, dilindungi throttle di routes).
 * Kandidat dicari/dibuat berdasarkan email (UNIQUE) — pelamar yang
 * sama boleh melamar posisi BERBEDA, tapi tidak boleh melamar
 * posisi yang SAMA dua kali (UNIQUE posting_id+candidate_id).
 */
final class SubmitApplication
{
    public function handle(
        string $postingId,
        string $fullName,
        string $email,
        ?string $phone,
        ?string $resumePath,
    ): string {
        return DB::transaction(function () use ($postingId, $fullName, $email, $phone, $resumePath) {
            $posting = DB::table('rec_job_postings')->where('id', $postingId)->first();

            if ($posting === null || ! $posting->is_open) {
                throw new DomainException('Lowongan ini sudah tidak menerima lamaran.');
            }

            $now = new DateTimeImmutable;

            $candidateId = DB::table('rec_candidates')->where('email', $email)->value('id');

            if ($candidateId === null) {
                $candidateId = (string) Uuid7::generate();

                DB::table('rec_candidates')->insert([
                    'id' => $candidateId,
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'resume_path' => $resumePath,
                    'source' => 'lowongan_publik',
                    'created_at' => $now,
                ]);
            } elseif ($resumePath !== null) {
                // Kandidat lama melamar lagi dengan CV baru — perbarui
                // agar HC selalu melihat berkas TERBARU dari kandidat itu.
                DB::table('rec_candidates')->where('id', $candidateId)->update(['resume_path' => $resumePath]);
            }

            $alreadyApplied = DB::table('rec_applications')
                ->where('posting_id', $postingId)
                ->where('candidate_id', $candidateId)
                ->exists();

            if ($alreadyApplied) {
                throw new DomainException('Anda sudah pernah melamar posisi ini.');
            }

            $applicationId = (string) Uuid7::generate();

            DB::table('rec_applications')->insert([
                'id' => $applicationId,
                'posting_id' => $postingId,
                'candidate_id' => $candidateId,
                'status' => 'melamar',
                'applied_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            return $applicationId;
        });
    }
}
