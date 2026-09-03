<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Membuat DAN langsung membuka lowongan dari satu job requisition
 * yang sudah disetujui — skema `rec_job_postings` hanya punya
 * `is_open` boolean (bukan status draft/terbuka/tertutup), jadi
 * "membuat" dan "menerbitkan" adalah SATU tindakan yang sama di
 * sini; menutup lowongan (`is_open=false`) cukup pembaruan field
 * sederhana di JobPostingController, tidak butuh kelas tersendiri.
 */
final class PublishJobPosting
{
    public function handle(
        string $requisitionId,
        string $title,
        string $description,
        string $requirements,
        string $employmentStatusOffered,
    ): string {
        $requisition = DB::table('rec_job_requisitions')->where('id', $requisitionId)->first();

        if ($requisition === null) {
            throw new DomainException('Requisition tidak ditemukan.');
        }

        if ($requisition->status !== 'approved') {
            throw new DomainException('Lowongan hanya dapat dibuka dari requisition yang sudah disetujui.');
        }

        $now = new DateTimeImmutable;
        $id = (string) Uuid7::generate();

        DB::table('rec_job_postings')->insert([
            'id' => $id,
            'requisition_id' => $requisitionId,
            'title' => $title,
            'description' => $description,
            'requirements' => $requirements,
            'employment_status_offered' => $employmentStatusOffered,
            'is_open' => true,
            'opened_at' => $now,
            'closed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        return $id;
    }
}
