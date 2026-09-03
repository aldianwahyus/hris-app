<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Mencatat hasil wawancara — menandai jadwal 'selesai'. */
final class RecordInterviewFeedback
{
    public function handle(string $interviewId, string $feedback, ?int $rating): void
    {
        $interview = DB::table('rec_interview_schedules')->where('id', $interviewId)->first();

        if ($interview === null) {
            throw new DomainException('Jadwal wawancara tidak ditemukan.');
        }

        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            throw new DomainException('Rating wawancara harus antara 1-5.');
        }

        DB::table('rec_interview_schedules')->where('id', $interviewId)->update([
            'feedback' => $feedback,
            'rating' => $rating,
            'status' => 'selesai',
            'updated_at' => new DateTimeImmutable,
        ]);
    }
}
