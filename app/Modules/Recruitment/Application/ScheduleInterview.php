<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Menjadwalkan wawancara untuk satu lamaran. */
final class ScheduleInterview
{
    public function handle(
        string $applicationId,
        DateTimeImmutable $scheduledAt,
        string $locationOrLink,
        ?string $interviewerEmployeeId,
    ): string {
        $application = DB::table('rec_applications')->where('id', $applicationId)->first();

        if ($application === null) {
            throw new DomainException('Lamaran tidak ditemukan.');
        }

        $now = new DateTimeImmutable;
        $id = (string) Uuid7::generate();

        DB::table('rec_interview_schedules')->insert([
            'id' => $id,
            'application_id' => $applicationId,
            'scheduled_at' => $scheduledAt,
            'location_or_link' => $locationOrLink,
            'interviewer_employee_id' => $interviewerEmployeeId,
            'status' => 'dijadwalkan',
            'feedback' => null,
            'rating' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }
}
